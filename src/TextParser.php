<?php

namespace Akademiano\UnstructuredTextParser;

use DirectoryIterator;

class TextParser
{
    public function parseText(string $text, string $templateContent, $templateByStrings = true)
    {
        $text = $this->prepareText($text, $templateByStrings);

        $templatePattern = $this->prepareTemplate($templateContent, $templateByStrings);
        $extractedData = $this->extractData($text, $templatePattern, $templateByStrings);

        if ($extractedData) {
            return $extractedData;
        }
        return null;
    }

    public function parseByTemplate(string $text, string $templatePath)
    {
        $templateContent = file_get_contents($templatePath);
        return $this->parseText($text, $templateContent);
    }

    public function parseByTemplatesDir(string $text, string $templatesDir, $findMatchingTemplate = true)
    {
        $templates = $this->getTemplates($text, $templatesDir, $findMatchingTemplate);

        foreach ($templates as $templateContent) {
            $extractedData = $this->parseText($text, $templateContent);
            if (null !== $extractedData) {
                return $extractedData;
            }
        }
        return null;
    }

    public function getDirectoryIterator($directory)
    {
        return new DirectoryIterator($directory);

    }

    protected function getTemplates(string $templatesDir, string $text, bool $findMatchingTemplate = true)
    {
        if ($findMatchingTemplate) {
            return $this->findTemplate($templatesDir, $text);
        }

        $templates = [];
        $directoryIterator = $this->getDirectoryIterator($templatesDir);
        foreach ($directoryIterator as $fileInfo) {
            $templates[$fileInfo->getPathname()] = file_get_contents($fileInfo->getPathname());
        }

        return $templates;
    }


    /**
     * Prepares the provided text for parsing by escaping known characters and removing exccess whitespaces
     *
     * @param string $txt ; The text provided by the user for parsing
     * @return string; The prepared clean text
     *
     */
    protected function prepareText($txt, $templateByStrings = false)
    {
        if (!$templateByStrings) {
            //Remove all multiple whitespaces and replace it with single space
            $txt = preg_replace('/\s+/', ' ', $txt);
        }

        return trim($txt);
    }

    /**
     * Prepares the matched template text for parsing by escaping known characters and removing excess whitespaces
     *
     * @param string $templateTxt ; The matched template contents
     * @return string; The prepared clean template pattern
     *
     */
    protected function prepareTemplate($templateTxt, $templateByStrings)
    {
        $patterns = [
            '/\\\{%(.*)%\\\}/U', // 1 Replace all {%Var%}...
        ];

        $replacements = [
            '(?<$1>.*)',         // 1 ...with (?<Var>.*)
        ];

        if (!$templateByStrings) {
            $patterns[] = '/\s+/';
            $replacements[] = ' ';
        }

        $templateTxt = preg_replace($patterns, $replacements, preg_quote($templateTxt, '/'));

        return trim($templateTxt);
    }

    /**
     * Extracts the named variables values from within the text based on the provided template
     *
     * @param string $text ; The prepared text provided by the user for parsing
     * @param string $template ; The template regex pattern from the matched template
     * @return array|bool; The matched data array or false on unmatched text
     *
     */
    protected function extractData($text, $template, $templateByStrings = true)
    {
        $modifers = '';
        if (!$templateByStrings) {
            //Extract the text based on the provided template using REGEX
            $templates = [$template];
            $modifers = '/s';
        } else {
            $templates = preg_split("/\r\n|\n|\r/", $template, -1, PREG_SPLIT_NO_EMPTY);
        }

        $fullMatches = [];
        foreach ($templates as $template) {
            preg_match('/' . $template . '/' . $modifers, $text, $matches);
            $fullMatches = array_merge($fullMatches, $matches);
        }
        $matches = $fullMatches;

        //Extract only the named parameters from the matched regex array
        $keys = array_filter(array_keys($matches), 'is_string');
        $matches = array_intersect_key($matches, array_flip($keys));

        if (!empty($matches)) {
            return $this->cleanExtractedData($matches);
        }

        return false;
    }

    /**
     * Removes unwanted stuff from the data array like html tags and extra spaces
     *
     * @param mixed $matches ; Array with matched strings
     * @return array; The clean data array
     *
     */
    protected function cleanExtractedData($matches)
    {
        return array_map([$this, 'cleanElement'], $matches);
    }


    /**
     * A callback method to remove unwanted stuff from the extracted data element
     *
     * @param string $value ; The extracted text from the matched element
     * @return string; clean/stripped text
     *
     */
    protected function cleanElement($value)
    {
        return trim(strip_tags($value));
    }

    /**
     * Iterates through the templates directory to find the closest template pattern that matches the provided text
     *
     * @param string $text ; The text provided by the user for parsing
     * @return array; The matched template contents with its path as a key or empty array if none matched
     *
     */
    protected function findTemplate($templatesDir, $text)
    {
        $matchedTemplate = [];
        $maxMatch = -1;

        $directoryIterator = $this->getDirectoryIterator($templatesDir);

        foreach ($directoryIterator as $fileInfo) {
            $templateContent = file_get_contents($fileInfo->getPathname());

            similar_text($text, $templateContent, $matchPercentage); //Compare template against text to decide on similarity percentage

            if ($matchPercentage > $maxMatch) {
                $maxMatch = $matchPercentage;
                $matchedTemplate = [$fileInfo->getPathname() => $templateContent];
            }
        }

        return $matchedTemplate;
    }
}
