<?php

class RecipeParser_Parser_101cookbookscom {

    static public function parse($html, $url) {
        $recipe = RecipeParser_Parser_Microformat::parse($html, $url);

        // Turn off libxml errors to prevent mismatched tag warnings.
        libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($doc);

        // Description
        $description = "";
        $nodes = $xpath->query('//div[@id="recipe"]/p/i');
        foreach ($nodes as $node) {
            $line = trim($node->nodeValue);
            if (strpos($line, "Adapted from") === false) {
                $description .= $line . "\n\n";
            }
        }
        $description = trim($description);
        $recipe->description = $description;

        // Ingredients
        $recipe->resetIngredients();
        $lines = array();

        // Add ingredients to blob
        $nodes = $xpath->query('//div[@id="recipe"]/blockquote/p');
        foreach ($nodes as $node) {
            foreach ($node->childNodes as $child) {
                $line = trim($child->nodeValue);
                switch($child->nodeName) {
                    case "strong":
                    case "b":
                        if (strpos($line, ":") === false) {
                            $line .= ":";
                        }

                        $lines[] = $line;
                        break;

                    case "#text":
                    case "div":
                    case "p":
                        $lines[] = $line;
                        break;
                }
            }
        }
        foreach ($lines as $line) {
            if (RecipeParser_Text::matchSectionName($line)) {
                $recipe->addIngredientsSection(RecipeParser_Text::formatSectionName($line));
            } else {
                $line = RecipeParser_Text::formatAsOneLine($line);
                $recipe->appendIngredient($line);
            }
        }
        
        // Instructions
        $recipe->resetInstructions();
        $lines = array();

        $nodes = $xpath->query('//div[@id="recipe"]/*');
        $passed_ingredients = false;
        foreach ($nodes as $node) {
            if ($node->nodeName == "blockquote") {
                $passed_ingredients = true;
                continue;
            }
            if ($node->nodeName == "p") {

                if ($passed_ingredients) {
                    $line = trim($node->nodeValue);

                    // Finished with ingredients once we hit "Adapted" notes or any <p> 
                    // with a class attribute.
                    if (stripos($line, "Adapted from") !== false) {
                        break;
                    } else if ($node->getAttribute("class")) {
                        break;
                    }

                    // Servings?
                    if (stripos($line, "Serves ") === 0) {
                        $recipe->yield = RecipeParser_Text::formatYield($line);
                        continue;
                    }

                    $recipe->appendInstruction(RecipeParser_Text::formatAsOneLine($node->nodeValue));
                }
            }
        }

        return $recipe;
    }

}

?>
