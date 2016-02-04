<?php

class RecipeParser_Parser_Bbccouk {

    static public function parse($html, $url) {
        // Get all of the standard hrecipe stuff we can find.
        $recipe = RecipeParser_Parser_Microformat::parse($html, $url);

        // Turn off libxml errors to prevent mismatched tag warnings.
        libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($doc);
        $recipe->resetIngredients();

        $recipeName = $xpath->query('.//*[@itemprop="name"]');
        $value = trim($recipeName[0]->nodeValue);
        $recipe->title = $value;

        $nodes = $xpath->query('//li[@itemprop="recipeInstructions"]/*');
        if ($nodes->length) {
            foreach ($nodes as $sub) {
                $line = trim($sub->nodeValue);
                $line = RecipeParser_Text::stripLeadingNumbers($line);
                $recipe->appendInstruction($line);
            }
        }
        $image = $xpath->query('.//*[@itemprop="image"]');
        $photo_url = $image[0]->getAttribute('src');
        $recipe->photo_url = RecipeParser_Text::relativeToAbsolute($photo_url, $url);

        // Meta data
        $nodes = $xpath->query('//div[@class="recipe-metadata-wrap"]/*');
        if ($nodes->length) {
            $prepTime = $xpath->query('.//*[@itemprop="prepTime"]');
            foreach ($prepTime[0]->attributes as $sub) {
                if($sub->nodeName=="content"){
                    $value = trim($sub->nodeValue);
                    $recipe->time['prep'] = RecipeParser_Text::iso8601ToMinutes($value);
                }
            }
            $prepTime = $xpath->query('.//*[@itemprop="cookTime"]');
            foreach ($prepTime[0]->attributes as $sub) {
                if($sub->nodeName=="content"){
                    $value = trim($sub->nodeValue);
                    $recipe->time['cook'] = RecipeParser_Text::iso8601ToMinutes($value);
                }
            }
            $recipe->time['total'] = $recipe->time['cook']+$recipe->time['prep'];

            $recipeYield = $xpath->query('.//*[@itemprop="recipeYield"]');
            $value = trim($recipeYield[0]->nodeValue);
            $recipe->yield = RecipeParser_Text::formatYield($value);

        }

        // Multi-stage ingredients
        $nodes = $xpath->query('//div[@class="recipe-ingredients-wrapper"]/*');
        if ($nodes->length) {

            foreach ($nodes as $node) {
                if ($node->nodeName == 'h3') {
                    $value = $node->nodeValue;
                    $value = RecipeParser_Text::formatSectionName($value);
                    $recipe->addIngredientsSection($value);
                
                } else if ($node->nodeName == 'ul') {
                    $subs = $xpath->query('.//li[@itemprop="ingredients"]', $node);
                    foreach ($subs as $sub) {
                        $value = trim($sub->nodeValue);
                        $recipe->appendIngredient($value);
                    }
                }

            }

        }

        return $recipe;
    }

}

?>
