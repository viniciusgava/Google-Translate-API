<?php

namespace GoogleTranslate;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class Client implements TranslateInterface, LanguagesInterface
{
    /**
     * API URI
     */
    const API_URI = 'https://www.googleapis.com/language/translate/v2';

    /**
     * Access key
     *
     * @var string
     */
    private $accessKey;

    /**
     * Http client
     *
     * @var \GuzzleHttp\ClientInterface
     */
    private $httpClient;

    /**
     * Client constructor.
     * @param string $accessKey
     * @param ClientInterface $httpClient
     */
    public function __construct($accessKey, ClientInterface $httpClient = null)
    {
        if (strlen($accessKey) !== 39) {
            throw new Exception\InvalidAccessKeyException();
        }

        $this->accessKey = $accessKey;

        $this->httpClient = new HttpClient();
        if ($httpClient) {
            $this->httpClient = $httpClient;
        }
    }

    /**
     * @return \GuzzleHttp\ClientInterface
     */
    private function getHttpClient() {
        return $this->httpClient;
    }

    /**
     * @inheritdoc
     */
    public function translate($text, $targetLanguage, &$sourceLanguage = null)
    {
        // used to return the same type of variable used in the text
        $onceResult = !is_array($text);

        // prepare the string
        $text = $this->prepareText($text);

        // validate if required fields has being filled.
        if (!$text) {
            throw new Exception\InvalidTextException();
        }

        if (!$this->isValidLanguage($targetLanguage)) {
            throw new Exception\InvalidTargetLanguageException();
        }

        // query params
        $query = [
            'key' => $this->accessKey,
            'q' => $text,
            'target' => $targetLanguage,
        ];

        // validate if is necessary to pass the source language.
        if ($sourceLanguage && !$this->isValidLanguage($sourceLanguage)) {
            throw new Exception\InvalidSourceLanguageException();
        }
        if ($sourceLanguage) {
            $query['source'] = $sourceLanguage;
        }

        try {
            // send request
            $query = $this->httpBuildQuery($query);

            $response = $this->getHttpClient()->request(
                'POST',
                self::API_URI,
                ['query' => $query]
            );
        } catch (GuzzleException $e) {
            throw new Exception\TranslationErrorException('Translation error: ' . $e->getMessage(), 4, $e);
        }

        // check response json
        $result = json_decode($response->getBody(), true);
        if (!is_array($result) || !array_key_exists('data', $result) || !array_key_exists('translations', $result['data'])) {
            throw new Exception\TranslationErrorException('Invalid response');
        }

        // prepare responses
        $translations = [];
        $sources = [];
        foreach ($result['data']['translations'] as $translation) {
            $translations[] = html_entity_decode($translation['translatedText'] , ENT_QUOTES, 'UTF-8');

            if (array_key_exists('detectedSourceLanguage', $translation)) {
                $sources[] = $translation['detectedSourceLanguage'];
            }
        }

        // add source language by reference if it was not passed.
        if (!$sourceLanguage) {
            $sourceLanguage = $onceResult ? current($sources) : $sources;
        }

        return $onceResult ? current($translations) : $translations;
    }

    /**
     * @inheritdoc
     */
    public function languages($targetLanguage = null)
    {
        if ($targetLanguage && !$this->isValidLanguage($targetLanguage)) {
            throw new Exception\InvalidTargetLanguageException();
        }

        // query params
        $query = [
            'key' => $this->accessKey,
        ];

        if ($targetLanguage) {
            $query['target'] = $targetLanguage;
        }

        try {
            // send request
            $query = $this->httpBuildQuery($query);

            $response = $this->getHttpClient()->request(
                'GET',
                self::API_URI . '/languages',
                ['query' => $query]
            );
        } catch (GuzzleException $e) {
            throw new Exception\LanguagesErrorException('Languages error: ' . $e->getMessage(), 5, $e);
        }

        // check response json
        $result = json_decode($response->getBody(), true);
        if (!is_array($result) || !array_key_exists('data', $result) || !array_key_exists('languages', $result['data'])) {
            throw new Exception\LanguagesErrorException('Invalid response');
        }

        return $result['data']['languages'];
    }

    /**
     * Create a query string
     *
     * @param array $params
     * @return string
     */
    private function httpBuildQuery($params)
    {
        $query = [];
        foreach ($params as $key => $param) {
            if (!is_array($param)) {
                continue;
            }
            // when a param has many values, it generate the query string separated to join late
            foreach ($param as $subParam) {
                $query[] = http_build_query([$key => $subParam]);
            }
            unset($params[$key]);
        }

        // join queries strings
        $query[] = http_build_query($params);
        $query = implode('&', $query);

        return $query;
    }

    /**
     * Prepare text to be processed
     *
     * @param string|array $text
     * @return array
     */
    private function prepareText($text)
    {
        // convert no array text to array
        if (is_array($text)) {
            $text = [$text];
        }

        return $text;
    }

    /**
     * is a valid language?
     *
     * @param string $language language to be validate
     * @return boolean
     */
    private function isValidLanguage($language) {
        $regexpValidLanguage = '%([a-z]{2})(-[a-z]{2})?%';

        return preg_match($regexpValidLanguage, $language) === 1;
    }
}
