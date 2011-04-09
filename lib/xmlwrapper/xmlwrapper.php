<?php
/**
 * Extends the DOMDocument to implement API XML support.
 *
 * @author JosŽ P. Airosa
 */
class XMLWrapper extends DOMDocument {
	public $dom,$tag;
	
	public function __construct($version,$charset) {
		$this->dom = parent::__construct($version,$charset);
	}
	
	public function fromMixed($mixed, $domElement = null, $first = true) {
		// Check if we're calling DOMDocument object or a user defined one
		$domElement = is_null($domElement) ? $this : $domElement;
		if($first) {
			// Check if we only have on set of results
			if(count($mixed) == 1) {
				// Add parent tag for the requested action
				$node = $this->createElement($this->tag);
				$domElement->appendChild($node);
				// Recursively call this function to build the XML with deep awareness
				$this->fromMixed($mixed, $node, false);
			} else {
				$this->fromMixed($mixed, $domElement, false);
			}
		} else {
			// ... or if we have more than one
			if (is_array($mixed) || is_object($mixed)) {
				foreach( $mixed as $index => $mixedElement ) {
					
					if ( is_int($index) ) {
						// Add parent tag for the requested action
						$node = $this->createElement($this->tag);
						$domElement->appendChild($node);
					} else {
						// Convert object/array indexes to XML Document objects
						$node = $this->createElement($index);
						$domElement->appendChild($node);
					}
					// Recursively call this function to build the XML with deep awareness  
					$this->fromMixed($mixedElement, $node, false);
				}
			} else {
				// Check if we are at the end of an object/array
				$domElement->appendChild($this->createTextNode($mixed));
			}
		}
	}
}
?>