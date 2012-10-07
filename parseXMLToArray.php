<?php
//
// parseXMLtoArray( ) -
//  
//  (C) 2006 Brad Broerman bbroerman@bbroerman.net
//  Released under the LGPL license - Use however you wish, as long as you keep this notice.
//
//  This function takes a string containing XML and parses it into an array representation. Each array element
// (Node) contains the following elements:
//
//   a) One element named "Children" that is an array of the names of all child nodes of this node.
//   b) One element named "Parameters" that is an array of the parameter names for this node.
//   c) One element named "Contents" that contains the plain text contents of the node.
//   d) One element named "NodeName" that contains the name (type) of the current node.
//   e) The node also contains the parameters, and child nodes as elements, referred to by their names.
//      the parameters are simple strings, and the nodes are arrays containing all of the above elements itself.
//
//  This makes it very easy to drill down into a simple XML document using just arrays, but also allows 
// greater flexability for complicated XML documents.
//
// Limitations: 1) Can not have child node that have the same name (type) as a parameter.
//              2) Can not have a parameter or child node with the names (types): "Contents", "Children", "NodeName", or "Parameters".
//
// To Do: 1) Improperly closed nodes: Backtrack to the last open node that has the same name, and roll-up. If
//           not found, ignore the close tag. Use the openNodeStack array to check this.
//        2) Add the rest of the error checking noted below in the comments.
//        3) Add support to XML escape codes.
//        4) Verify character set handling.
//
//

function parseXMLtoArray( $inputString )
{
    $lastEndPos = 0;          // This is where we left off on the last iteration...
    $outputStack = array();    // This is where we will be assembling out output nodes.

    //
    // Start by having a "dummy" parent node...
    //
    $newNode = array();
    $newNode['Children'] = array();

    array_push( $outputStack, $newNode );   

    //
    // First, let's trim out any whitespace...
    //

    $inputString = trim($inputString);

    //
    // We will iterate across the entire input string searching for tags.
    //

    while( $lastEndPos < strlen($inputString) )
    {
        //   
        // Start by looking for the next open bracket
        //

        $startPos = strpos( $inputString, "<", $lastEndPos );

        //
        // Found a node tag.
        //

        if ( FALSE !== $startPos )
        {
            //
            // Find the end of the element declaration.
            //

            $closePos = strpos( $inputString, ">", $startPos);

            //
            // Check for an error here... If there are *bad* characters between the start
            // and close positions, quit, and return FALSE. This would include any open
            // brackets, an imbalance of quotes, etc.
            //


            //
            // Is this a close tag?
            //

            $isCloseTag = ( substr($inputString, $startPos + 1, 1) == "/" )?true:false;

            //
            // Is it a self-closing open tag?
            //
            $isSelfClosingTag = ( substr($inputString, $closePos - 1, 1) == "/" )?true:false;

            //
            // IF this tag begins and ends with a ? treat it as a self-closing tag...
            // we also want to ignore the opening ? in the tag...
            //    
            if( substr($inputString, $startPos + 1, 1) == "?" )
            {
                $isSelfClosingTag = ( substr($inputString, $closePos - 1, 1) == "?" )?true:false;
                $startPos++;
            }

            //
            // Now, get the tag name (from char[0] to the 1st whitespace)
            //
            $tagEndPos = 0;
            for($tagEndPos = $startPos + 1; $tagEndPos < $closePos ; $tagEndPos++ )
            {
                $tmp = substr($inputString,$tagEndPos,1);
                if( $tmp == " " || $tmp == "\t" || $tmp == "\n" || $tmp == "\x0B" )
                    break;
            }

            $tagName = substr( $inputString, $startPos + ($isCloseTag?2:1), $tagEndPos - $startPos - ($isCloseTag?2:1) );
            
            //
            // Check to see if we are closing an open element... or opening a new element.
            //
            if( $isCloseTag )
            {
                //
                // A Close tag will not have any parameters, so let's make sure we don't
                //


                //
                // This is a close tag. we should make sure it's the last one we opened.
                //
                $CurrNode = array_pop( $outputStack );

                if( $CurrNode && $CurrNode['Nodename'] == $tagName )
                {
                    //
                    // Else, we take everything from the start of the input string, to the
                    // beginning of this close tag, and append it to the parent node as 
                    // "Contents", as long as there is a string value...
                    //    

                    // Check to see if there was any text between the $lastEndtPos (the char after
                    // the last close bracket)

                    $checkStr = "";
                    if( $lastEndPos < $startPos )
                        $checkStr = trim(substr($inputString, $lastEndPos + 1, $startPos - $lastEndPos - 1));
                    
                    if( strlen( $checkStr ) > 0 )
                    {
                        $CurrNode['Contents'] = $checkStr;

                        str_replace("&amp;","&",$CurrNode['Contents']);
                        str_replace("&lt;","<",$CurrNode['Contents']);
                        str_replace("&gt;",">",$CurrNode['Contents']);
                        str_replace("&apos;","'",$CurrNode['Contents']);
                        str_replace("&quot;","\"",$CurrNode['Contents']);
                    }

                    //
                    // Now, add the current node to the parent node as a child...
                    //

                    $parentNode = array_pop( $outputStack );                    

                    if( $parentNode )
                    {
                        if( !isSet( $parentNode[$tagName] ) )
                        {
                            $parentNode[$tagName] = $CurrNode;

                            array_push( $parentNode['Children'], $tagName );
                        }
                        else
                        {
                            //
                            // Determine how many children of the current parent share the same type.
                            //

                            $countOfChildren = 1;
                            for( $countOfChildren = 1; 
                                 isSet( $parentNode[$tagName."_".$countOfChildren] ); 
                                 $countOfChildren++ )
                            ;
                               
                            $parentNode[$tagName][$tagName."_".$countOfChildren] = $CurrNode;

                            array_push( $parentNode['Children'], $tagName."_".$countOfChildren );
                        }
                        
                        array_push( $outputStack, $parentNode );
                    }
                    else
                    {
                        array_push( $outputStack, $CurrNode );    
                    }              

                }
                else
                {

                    //
                    // If we are not closing the last opened element, flag an error.
                    //


                    //
                    // And for safety, let's put the current node back on the stack.
                    //

                    if( $CurrNode )
                    {
                        array_push( $outputStack, $CurrNode );   
                    }
                }            
            }
            else
            {
                //
                // This is an open tag element... so there should be no text between the $lastEndtPos
                // of the input string, and this start tag (except for whitespace).
                // If there is, we have an error...
                //
                

                //
                // If we're OK, lets create the new node.
                //

                $newNode = array();
                $newNode['Nodename'] = $tagName;
                $newNode['Children'] = array();
                $newNode['Parameters'] = array();
                $newNode['Contents'] = "";

                //
                // Now, we can use the $inputText from $tagEndPos + 1 to $closePos to parse the parameters...
                //
                
                $parameters = array();
                $indx = $tagEndPos + 1;
                $lastws = $indx;
                $lasteq = $indx; 
                $attrName = "";
                $arrtf = false;
                $eqf = false;
                $escaped = false;
                $doubleQuote = false;
                $singleQuote = false;
                
                for( ; $indx < $closePos - ( $isSelfClosingTag ? 1 : 0); $indx++ )
                {
                    $tmp = substr($inputString,$indx,1);

                    if( $tmp == "\\" && $escaped == false )
                    {
                        $escaped = true;
                    }
                    elseif( $tmp == '"' && $escaped == false && $singleQuote == false )
                    {
                        $doubleQuote = $doubleQuote?false:true;

                        //
                        // If $atrf is true, and we get in here (unless we're closing), this should be an error...
                        //

                        if( $attrf && false == $doubleQuote)
                        {
                            //
                            // Ok, this is the closing quote for the attribute, and after
                            // the start of the attribute text... Everything from the equals to here
                            // is the attribute text...
                            //

                            $attrf = false;
                            $eqf = false;
                
                            $parameters[$attrName] = substr( $inputString, $lasteq + 1, $indx - $lasteq - 1);

                            $attrName = "";
                        }
                        elseif( $attrf )
                        {
                            //
                            // ERROR
                            //
                        }
                        elseif( $eqf && true == $doubleQuote)
                        {
                            $attrf = true;
                            $lasteq = $indx;
                        }

                    }
                    elseif( $tmp == "'" && $escaped == false && $doubleQuote == false )
                    {
                        $singleQuote = $singleQuote?false:true;

                        //
                        // If $atrf is true, and we get in here (unless we're closing), this should be an error...
                        //

                        if( $attrf && false == $singleQuote)
                        {
                            //
                            // Ok, this is the closing quote for the attribute, and after
                            // the start of the attribute text... Everything from the equals to here
                            // is the attribute text...
                            //

                            $attrf = false;
                            $eqf = false;
                
                            $parameters[$attrName] = substr( $inputString, $lasteq + 1, $indx - $lasteq - 1);

                            $attrName = "";
                        }
                        elseif( $attrf )
                        {
                            //
                            // ERROR
                            //
                        }
                        elseif( $eqf && true == $singleQuote)
                        {
                            $attrf = true;
                            $lasteq = $indx;
                        }
                    }
                    elseif( $tmp == "=" && false == $doubleQuote && false == $singleQuote)
                    { 
                        //
                        // We have the equals... Everything since the lastws to here should be the attribute name.
                        //
                        
                        $attrName = trim(substr($inputString, $lastws, $indx - $lastws));
                        $lasteq = $indx;
                        $escaped = false;
                        $eqf = true;
                        $attrf = false;
                    }
                    elseif( ( $tmp == " " || $tmp == "\t" || $tmp == "\n" || $tmp == "\x0B" ) &&
                            false == $doubleQuote && false == $singleQuote )
                    {
                        $lastws = $indx; 
                        $escaped = false;

                        if( $attrf )
                        {
                            //
                            // Ok, this is the 1st un-quoted whitespace after the equals, and after
                            // the start of the attribute text... Everything from the equals to here
                            // is the attribute text...
                            //

                            $attrf = false;
                            $eqf = false;
                
                            $parameters[$attrName] = substr( $searchText, $lasteq + 1, $indx - $lasteq - 1);

                            $attrName = "";
                        }
                    }
                    else
                    {
                        if( $eqf )
                        {
                            $attrf = true;
                        }

                        $escaped = false;
                    }
                }

                //
                // Parameters should be name=value, where value may or may not have single or double quotes.
                //

                foreach( $parameters as $parmName => $parmValue )
                {
                    $newNode[$parmName] = $parmValue;

                    str_replace("&amp;","&",$newNode[$parmName]);
                    str_replace("&lt;","<",$newNode[$parmName]);
                    str_replace("&gt;",">",$newNode[$parmName]);
                    str_replace("&apos;","'",$newNode[$parmName]);
                    str_replace("&quot;","\"",$newNode[$parmName]);

                    array_push( $newNode['Parameters'], $parmName );
                }

                //
                // If the close bracket for the node was a /> then we won't have a close tag.
                //

                if( $isSelfClosingTag )
                {
                    //
                    // If we have a self-closing tag, just add the node to the parent, and move on.
                    //                    

                    $parentNode = array_pop( $outputStack );                    

                    if( $parentNode )
                    {
                        if( !isSet( $parentNode[$tagName] ) )
                        {
                            $parentNode[$tagName] = $newNode;

                            array_push( $parentNode['Children'], $tagName );
                        }
                        else
                        {
                            //
                            // Determine how many children of the current parent share the same type.
                            //

                            $countOfChildren = 1;
                            for( $countOfChildren = 1; 
                                 isSet( $parentNode[$tagName."_".$countOfChildren] ); 
                                 $countOfChildren++ )
                            ;
                               
                            $parentNode[$tagName][$tagName."_".$countOfChildren] = $newNode;

                            array_push( $parentNode['Children'], $tagName."_".$countOfChildren );
                        }

                        array_push( $outputStack, $parentNode );
                    }
                    else
                    {
                        array_push( $outputStack, $newNode );    
                    }
                }
                else
                {
                    //
                    // Else, if there is more following (with a separate close tag)
                    // we take the current newNode and push it onto the outputStack as the new end.
                    // 

                    array_push( $outputStack, $newNode );
                }
            }
        }
        else
        {
            //
            // If no open bracket is found (i.e. there is just text left in the string and no additional 
            // nodes), we should set $closePos to the end of the input string, and flag an error.
            //
 
            $closePos = strlen($inputString); 
        } 

        //
        // Prepare for the next iteration...
        //

        $lastEndPos = $closePos;
    }

    //
    // Now, we should double-check and return the outputStack array. 
    //

    return $outputStack;
}
?>

