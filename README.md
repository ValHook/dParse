# dParse
![Last tested](https://img.shields.io/badge/last--tested-May%208th%2C%202016-brightgreen.svg)
![Latest stable version](https://img.shields.io/badge/latest--stable--version-1.0-brightgreen.svg)
![Composer](https://img.shields.io/badge/composer-valhok%2Fdparse-yellowgreen.svg)
![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)
![Min PHP Version](https://img.shields.io/badge/min--php--version-v5.0.0-orange.svg)
![Dependencies](https://img.shields.io/badge/dependencies-cURL-orange.svg)

dParse is a strong jQuery-like HTML/XML parser written in PHP. I initiated this project when I realized that the current parsers ([Simple HTML DOM](http://simplehtmldom.sourceforge.net), [Ganon](https://code.google.com/p/ganon/), etc ...) that we can find on the Internet could be improved. So I decided to create a PHP parser that is better by improving the following points:  
  - Speed
  - Features
  - Flexibility
  - Memory use

## Installation
When you are in your root directory, you can just run this command to add this package on your app
```bash
composer require valhook/dparse
```
Or add this package to your `composer.json`
```json
{
    "valhook/dparse":"*"
}
```

## Usage
The information below explains how to use the different functionalities of dParse.

### Creating the DOM
```php
$dom = createdParseDOM($source, $args);
```
+ **$source** can be a remote URL, a raw HTML/XML source code or a local filepath.
+ **$args** is an optional parameter that is an array specifying advanced options.
Below are the default args. 

```php
$defaultargs = array("method" => "GET", // just concatenate the url and the http body in the source parameter and specify here the HTTP Method
                     "fake_user_agent" => NULL,
                     "fake_http_referer" => NULL,
                     "force_input_charset" => NULL,
                     "output_charset" => NULL,
                     "strip_whitespaces" => false,
                     "connect_timeout" => 10,
                     "transfer_timeout" => 40,
                     "verify_peer" => false,
                     "http_auth_username" => NULL,
                     "http_auth_password" => NULL,
                     "cookie_file" => NULL,
                     "is_xml" => FALSE,
                     "enable_logger" => FALSE);

```

### Or just retrieving the raw contents
```php
$contents = dParseGetContents($source, $args);
```

### Raw content and DOM level operations
The DOM object provides the following functions
```php
$dom->getRawContent(); or $dom // output is a string
$dom->showRawContent(); // echoes the raw content
$dom->saveRawContent($filename); // writes the content to a file
$dom->getSize(); // output is an int of the byte size of the content
$dom->setWhitepaceStripping($bool); // Tells dParse to strip all extra whitespaces whenever a string is returned or echoed.
$dom->getWhitespaceStripping(); // Get the current whitespace stripping status
$dom->setInputCharset($charset); // Tells dParse which charset should be used to interprate the document data, by default it is deduced from the HTTP/HTML headers
$dom->getInputCharset();
$dom->setOutputCharset($charset); // Tells dParse if a charset translation should be done when echoing or returning a string computed from the original DOM, by default no translation is done so the output charset is the same as the input's.
$dom->getOutputCharset();
$dom->getNoise(); // Return an array of string of unparsed data
        /* Noise regexes used by dParse, you may add yours at line 270 */
        $noise = array("'<!--(.*?)-->'is",
                        "'<!DOCTYPE(.*?)>'is",
                        "'<!\[CDATA\[(.*?)\]\]>'is",
                        "'(<\?)(.*?)(\?>)'s",
                        "'(\{\w)(.*?)(\})'s"
                        );
```
### Getting DOM nodes
DOM nodes/tags are queried with CSS selectors like jQuery
```php
$nodes_that_are_images = $dom('img');
$nodes_that_are_link_with_btn_class = $dom('a.btn');

// Most of the CSS3 selecting standard is supported
$nodes = $dom('a + a');
$nodes = $dom('div ~ div');
$nodes = $dom('div > p');
$nodes = $dom('ul ul > li');
$nodes = $dom('input[type=text]');
$nodes = $dom('img[src^="https"]');
$nodes = $dom('img[src$=".jpg"]');
$nodes = $dom('a[href*=google]');
$nodes = $dom('body > *');
// Of course it is not funny if you cannot combine them
$nodes = $dom('article[class*=post] section > div + div.classy, #some_id ~ .classy');

// Getting the root element
$rootnode = $dom->root();

// Remaining bugs
// Multiple classes are not supported, use:
$nodes = $dom('a[class="btn btn-primary"]') /* instead of */ $nodes = $dom('a.btn.btn-primary');

/// Pseudo selectors, like :not, :first-child etc are not yet supported

// For PHP < 5.3 users, use:
$dom->find('foo'); /* instead of */ $dom('foo');
```

### The MetaNode Object
+ The CSS-like selecting query returns a MetaNode object which is a set of different nodes.
+ `$dom('div');` will give you a MetaNode object contaning n Node Objects

Before we see how interact with the nodes within the MetaNode Object we will see all the MetaNode Object operations.

```php
$nodes->merge($othernodes); // Returns a new MetaNode Object containing the union of all the nodes from both MetaNodes
$nodes->length(); // Returns the number of nodes inside this meta node.
$nodes->eq($n); /* or */ $nodes->elem($n); // Returns the nth node of this MetaNode.
    // If n is a metanode or node it will return the interesction of both sets.
```

##### Versatility of the MetaNode
+ A MetaNode is an interface between you and the nodes of the DOM you requested to handle multi-nodes operations. 
+ If you have multiple nodes, you can pass the MetaNode Node-level functions to all of its nodes, and it will return a result set in an array containing the response from all the nodes.
```php
/* Example */
$dom('a')->text(); // will return array("foo", "bar", "baz", ...)
```
+ However if your MetaNode contains only one node, you can directly use your MetaNode as a **single** node and call its different functions that are listed below.
```php
/* Example */
$dom('#unique-id')->text(); // will return "foo" and not array("foo")
```
+ But if you do not want to use this versatility, or if you don't know how many nodes you have, you can iterate through the MetaNode with a `foreach` **even though it contains only one node**.
```php
foreach($nodes as $node) {
    // $node->do_something();
}
```
+ You can pass **any** Node-level function to your MetaNode. Node-level functions are listed right below.


### The Node Object
+ A node is an HTML tag. `<a ...>Foo</a>` is a node.
+ The node object can be used to extract or modify its contents.
+ If a modification is made it will directly update the whole DOM.

#### Getters
The getters are for the most part the same as jQuery's.
```php
$node->_dom(); // Returns the DOM linked to this node.
$node->index(); // Returns the index of the node in the DOM. HTML is 0, HEAD is 1, TITLE can be 2 etc...
$node->length(); // Always returns 1, it is an compatibility abstraction with the MetaNode object.
$node->tagName(); or $node->name(); // Returns the tag name (a, li, div etc...)
$node->attributes(); // Returns a dictionary of the attributes
   /* Example:
    array(2) {
    ["href"]=>
    string(8) "#contact"
    ["class"]=>
    string(23) "btn border-button-black"
    }
    Therefore you will get an array of array if you call it from a MetaNode
    */
$node->XXXX; // Will return the content of an attribute, examples:
    $node->href;
    $node->src;
    $node->type;
    $node->attr('XXXX'); or $node->prop('XXXX'); /* it is the same as */ $node->XXXX;
$node->depth(); // Will return the depth (int) of the node inside the DOM
$node->breadcrumb(); // Will return the full path from the root element to this node
$node->breadcrumb_size(); // Returns the size of the breadcrumb
$node->breadcrumb_element($i); // Returns a sub-element of the node's breadcrumb
$node->val(); // Same as $node->value, *I Will later add support for textareas and selects as the value attribute is irrelevant for them
$node->html(); // Returns the inner HTML of the node as a string
$node->htmlLength();
$node->outerHTML(); // Returns the outer HTML of the node
$node->outerHTMLLength();
$node->text(); // Returns the inner text, therefore the inner HTML with HTML tags stripped
$node->textLenght();
$node; // This is the __toString method, it is the same as $node->outerHTML();
```

#### CSS Sub-querying
+ Just like jQuery, you can perform CSS queries from a node instead of from the whole DOM.
+ If you apply any of the CSS sub-querying functions on a MetaNode, **you will get a MetaNode that is the union of the result of the same query applied to each node**.

##### Type of selectors
* **No selector**: The method takes no parameter
* **Selector**: The method takes a CSS Selector parameter
* **SmartSelector**: The method takes a parameter that can be:
    1. **A CSS Selector**
    2. **A MetaNode or Node** (ex: parentsUntil($node)) will return all the parents until one matches the specified node or is part of the MetaNode
    3. ***Occasionnaly if that makes sense: An INT*** (ex: parentsUntil(2)) will return the first 2 parents

##### Methods
```php
$node->find($smartselector); // Finds the subnodes of this node matching this CSS selector
$node->parent($smartselector = NULL); // Returns the first parent, or the parents that match the selector
$node->parents(); // Returns all parents
$node->parentsUntil($smartselector); // Return all the parents until the selector
$node->prev($smartselector = NULL); // Returns the first previous element, same depth level, or the previous one that matches the selector.
$node->prevAll();
$node->prevUntil($smartSelector);
$node->next($smartselector = NULL);
$node->nextAll();
$node->nextUntil($smartselector);
$node->children($smartselector = NULL); // If the selector is empty it returns all the children, if it is an int *i* it returns the first i children in the order of declaration inside the DOM, if it is a CSS selector or a MetaNode it returns the children that intersect with the CSS selector or the nodes.
$node->is($smartselector); // Returns itself (castable to true) for chaining purposes or false according to wether the node is part of the metanode or the results of the css query
$node->has($smartselector = NULL); or $node->hasChild($smartselector = NULL); // Returns itself or false
$node->hasParent($smartselector = NULL);
$node->hasPrev($smartselector = NULL);
$node->hasNext($smartselector = NULL);
```

#### Setters
+ Just like jQuery, they modify directly the node or metanode, all other instances of this node, and the DOM.
+ They are not fully finished, so far I have only made four methods

```php
$node->XXXX = "YYYY"; or $node->attr('XXXX', 'YYYY'); or $node->prop('XXXX', 'YYYY'); // Changes an attribute
$node->addClass($class);
$node->removeClass($class);
$node->setTagName($name); // Changes the tag name, ex span to div;
```

**That's it for the Node and Meta Nodes !**

## Logger
+ dParse bundles a logger for debugging purposes.
+ The logger is disabled by default but you can enable it when creating the DOM (it is on the of specifiable arguments) or later via the Logger API

#### Methods
```php
$dom->getLogger(); // Returns the logger object
$logger->isEnabled(); // Tells wether the logger is enabled
$logger->enable($bool); // Enables or disables the logger
$logger->getLogs(); // Returns an array of strings that are the logs
$logger->getLastLog(); // Returns the last entry in the logbook
$logger->clear(); // Clears all the logs
$logger->showLogs(); // Echoes all the logs
$logger->saveLogs($filename); // Writes all the logs to a file
$logger->log($message); // Logs a message if the logger is enabled
```

## Practical Examples using dParse
### Getting the headlines of a Wikipedia article
```php
include "dParse.php";
$wiki_root = "https://fr.wikipedia.org/wiki/";
$article = "Batman";
$doc = createDParseDOM($wiki_root.$article, array("strip_whitespaces", true));
$contents = $doc('#bodyContent')->children('h1, h2, h3, h4, h5, h6')->text();
print_r($contents);
```
outputs:
```
Array
(
    [0] => Sommaire
    [1] => Origines du personnage[modifier | modifier le code]
    [2] => Évolution du personnage[modifier | modifier le code]
    [3] => De 1939 à 1964[modifier | modifier le code]
    [4] => De 1964 à 1986[modifier | modifier le code]
    [5] => Batman moderne[modifier | modifier le code]
    [6] => La renaissance DC[modifier | modifier le code]
    [7] => Description[modifier | modifier le code]
    [8] => Personnalités[modifier | modifier le code]
    [9] => Bruce Wayne[modifier | modifier le code]
    [10] => Batman[modifier | modifier le code]
    [11] => Matches Malone[modifier | modifier le code]
    [12] => Équipement[modifier | modifier le code]
    [13] => Univers[modifier | modifier le code]
    [14] => Lieux[modifier | modifier le code]
    [15] => Gotham City[modifier | modifier le code]
    [16] => Batcave[modifier | modifier le code]
    [17] => L'asile d'Arkham[modifier | modifier le code]
    [18] => Alliés[modifier | modifier le code]
    [19] => Robin[modifier | modifier le code]
    [20] => Alfred Pennyworth[modifier | modifier le code]
    [21] => Lucius Fox[modifier | modifier le code]
    [22] => Le commissaire James Gordon[modifier | modifier le code]
    [23] => Batgirl[modifier | modifier le code]
    [24] => Ace[modifier | modifier le code]
    [25] => Relation avec les autres super-héros[modifier | modifier le code]
    [26] => Les équipes de super-héros[modifier | modifier le code]
    [27] => Relations entre Batman et Superman[modifier | modifier le code]
    [28] => Vie sentimentale[modifier | modifier le code]
    [29] => Dans les comics[modifier | modifier le code]
    [30] => Dans les films[modifier | modifier le code]
    [31] => Ennemis[modifier | modifier le code]
    [32] => Analyses et critiques[modifier | modifier le code]
    [33] => Analyses[modifier | modifier le code]
    [34] => Batman justicier[modifier | modifier le code]
    [35] => Approche psychanalytique[modifier | modifier le code]
    [36] => Critiques[modifier | modifier le code]
    [37] => Séries de comics[modifier | modifier le code]
    [38] => Autres média[modifier | modifier le code]
    [39] => Radio[modifier | modifier le code]
    [40] => Serials[modifier | modifier le code]
    [41] => Série télévisée[modifier | modifier le code]
    [42] => Batman[modifier | modifier le code]
    [43] => Gotham[modifier | modifier le code]
    [44] => Dessins animés[modifier | modifier le code]
    [45] => Longs métrages[modifier | modifier le code]
    [46] => Premier long métrage[modifier | modifier le code]
    [47] => Tétralogie des années 1990[modifier | modifier le code]
    [48] => Trilogie de Christopher Nolan[modifier | modifier le code]
    [49] => DC Cinematic Universe[modifier | modifier le code]
    [50] => Jeux vidéo[modifier | modifier le code]
    [51] => Produits dérivés[modifier | modifier le code]
    [52] => Notes et références[modifier | modifier le code]
    [53] => Notes[modifier | modifier le code]
    [54] => Références bibliographiques[modifier | modifier le code]
    [55] => Autres références[modifier | modifier le code]
    [56] => Ouvrages[modifier | modifier le code]
    [57] => Articles[modifier | modifier le code]
    [58] => Articles connexes[modifier | modifier le code]
    [59] => Voir aussi[modifier | modifier le code]
    [60] => Liens externes[modifier | modifier le code]
)
```

### Getting the video search results & links on youtube
```php
$youtube_root = "https://www.youtube.com/results?search_query=";
$search = urlencode("funny potato");
$doc = createDParseDOM($youtube_root.$search);
$doc->setWhitespaceStripping(true);
$links = $doc('h3 a[href*=watch]');
$out = array();
foreach ($links as $l)
    $out[] = array("title" => $l->text(), "url" => $l->href);
    
print_r($out);
```
outputs:
```
Array
(
    [0] => Array
        (
            [title] => Funny Ferret Steals a Potato
            [url] => /watch?v=7IXi_ANNMC8
        )

    [1] => Array
        (
            [title] => A Potato Flew Around My Room Before You Came Vine Compilation
            [url] => /watch?v=41uD91e4GqA
        )

    [2] => Array
        (
            [title] => Garry&#39;s Mod | POTATO HIDE AND SEEK | Funny Potato Mod
            [url] => /watch?v=ri0dw7gLn14
        )

    [3] => Array
        (
            [title] => Play Doh Mr Potato Head Make Funny Faces Grow Hair Disney Play-Doh Pixar Toy Story &amp; Cookie Monster
            [url] => /watch?v=u-4o8oTjAF8
        )

    [4] => Array
        (
            [title] => The Potato Song
            [url] => /watch?v=hUhK8sS9gnY
        )

    [5] => Array
        (
            [title] => Pranks Funny : Funny Potato Cannon Prank
            [url] => /watch?v=mkT1tUqEqZw
        )

    [6] => Array
        (
            [title] => A Potato Flew Around My Room Before You Came Vine Compilation 2014
            [url] => /watch?v=L-gSakeOFqM
        )

    [7] => Array
        (
            [title] => Zombie Potatoes (Call of Duty WaW Zombies Custom Maps, Mods, &amp; Funny Moments)
            [url] => /watch?v=WcI5A4J0E0g
        )

    [8] => Array
        (
            [title] => Lord of the Rings funny (potato) edit
            [url] => /watch?v=57wK0JugLac
        )

    [9] => Array
        (
            [title] => DVBBS - Angel (DJ Potato Remix) [w/ Funny Old Man Laughing]
            [url] => /watch?v=MFWBprP1o5o
        )

    [10] => Array
        (
            [title] => GTA 5 Funny Gameplay Moments! #5 - New &quot;Swingset&quot; Glitch, Hot Potato, and More! (GTA Cannon Glitch)
            [url] => /watch?v=Ql8ovzDzx1E
        )

    [11] => Array
        (
            [title] => Potato Suicide
            [url] => /watch?v=La-FSCfQEsk
        )

    [12] => Array
        (
            [title] => CS GO BIGGEST WTF! FUNNY MOMENTS - Road to Global Potato ELITE COUNTER STRIKE MATCH MAKING
            [url] => /watch?v=AtLjUXDK9Vw
        )

    [13] => Array
        (
            [title] => Funny Potato Gun Fail
            [url] => /watch?v=ciGCXWWfjpU
        )

    [14] => Array
        (
            [title] => Call of Duty WaW Zombies Potato Edition! (Custom Map &amp; Funny Moments)
            [url] => /watch?v=fBeFRbmb_rg
        )

    [15] => Array
        (
            [title] => Funny Potato Video
            [url] => /watch?v=BybCDVD3Y-E
        )

    [16] => Array
        (
            [title] => Mr Potato Head, Pirate Island Costume - Create &amp; Play Funny Movies Cartoon iPad, iPhone
            [url] => /watch?v=4JINBHAi0vQ
        )

    [17] => Array
        (
            [title] => Battlefield 4 Random Moments 63 (Soldier Squishing, Dip Dip Potato Chip!)
            [url] => /watch?v=z6aSCVzIFM0
        )

    [18] => Array
        (
            [title] => The Potato Song
            [url] => /watch?v=q7uyKYeGPdE
        )

)
```
# Happy parsing !






