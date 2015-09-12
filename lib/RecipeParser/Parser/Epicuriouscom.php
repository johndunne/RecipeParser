<?php

class RecipeParser_Parser_Epicuriouscom {

    static public function parse($html, $url) {
        $recipe = RecipeParser_Parser_MicrodataSchema::parse($html, $url);

        // Turn off libxml errors to prevent mismatched tag warnings.
        libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($doc);


        // OVERRIDES for epicurious
        // Prep Times
        $nodes = $xpath->query('//*[@class="summary_data"]');
        if ($nodes->length) {
            foreach ($nodes as $node) {
                if( preg_match('/ACTIVE/', $node->nodeValue) ){
                    $ing_nodes = $node->childNodes;
                    foreach ($ing_nodes as $ing_node) {
                        if($ing_node->nodeName == "span"){
                            $recipe->prep_time = RecipeParser_Text::formatAsOneLine($ing_node->nodeValue);
                        }
                    }
                }
                else if( preg_match('/TOTAL/', $node->nodeValue) ){
                    $ing_nodes = $node->childNodes;
                    foreach ($ing_nodes as $ing_node) {
                        if($ing_node->nodeName == "span"){
                            $recipe->total_time = RecipeParser_Text::formatAsOneLine($ing_node->nodeValue);
                        }
                    }
                }
            }
        }

        // Total Time
        $nodes = $xpath->query('//*[@itemprop="totalTime"]');
        if ($nodes->length) {
            $value = $nodes->item(0)->getAttribute("content");
            $recipe->time['total'] = RecipeParser_Text::iso8601ToMinutes($value);
        }

        // Ingredients
        $recipe->resetIngredients();
        $nodes = $xpath->query('//div[@id = "ingredients"]/*');
        foreach ($nodes as $node) {

            // <strong> contains ingredient section names
            if ($node->nodeName == 'strong') {
                $line = RecipeParser_Text::formatSectionName($node->nodeValue);
                $recipe->addIngredientsSection($line);
                continue;
            }

            // Extract ingredients from inside of <ul class="ingredientsList">
            if ($node->nodeName == 'ul') {
                // Child nodes should all be <li>
                $ing_nodes = $node->childNodes;
                foreach ($ing_nodes as $ing_node) {
                    if ($ing_node->nodeName == 'li') {
                        $line = trim($ing_node->nodeValue);
                        $recipe->appendIngredient($line);
                    }
                }
            }
        }

        return $recipe;
    }

}
