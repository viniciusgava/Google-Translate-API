<?php

namespace GoogleTranslate\Tests;

use GoogleTranslate\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    const ACCESS_KEY = 'HOHilKG4n7hzKc9xWRrZMfO5xvZpgcvBM1gCebf';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var ClientInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClientMock;

    public function setUp()
    {
        $this->httpClientMock = $this->createMock(ClientInterface::class);
        $this->client = new Client(self::ACCESS_KEY, $this->httpClientMock);
    }

    public function tearDown()
    {
        $this->client = null;
    }

    /**
     * @expectedException \GoogleTranslate\Exception\InvalidAccessKeyException
     * @expectedExceptionMessage Invalid access key
     * @expectedExceptionCode 1
     */
    public function testInstanceClientWithInvalidAccessKeyShouldReturnInvalidAccessKeyException()
    {
        $this->client = new Client('asd');
    }

    /**
     * @expectedException \GoogleTranslate\Exception\InvalidTextException
     * @expectedExceptionMessage Invalid text
     * @expectedExceptionCode 2
     */
    public function testTranslateMethodWithInvalidTextShouldReturnInvalidTextException()
    {
        $this->client->translate(null, 'en');
    }

    /**
     * @expectedException \GoogleTranslate\Exception\InvalidTargetLanguageException
     * @expectedExceptionMessage Invalid target language
     * @expectedExceptionCode 3
     */
    public function testTranslateMethodWithInvalidTargetLanguageShouldReturnInvalidTargetLanguageException()
    {
        $this->client->translate('How are you?', '12');
    }

    /**
     * @expectedException \GoogleTranslate\Exception\InvalidSourceLanguageException
     * @expectedExceptionMessage Invalid source language
     * @expectedExceptionCode 3
     */
    public function testTranslateMethodWithInvalidSourceLanguageShouldReturnInvalidSourceLanguageException()
    {
        $sourceLanguage = '34';
        $this->client->translate('How are you?', 'pt', $sourceLanguage);
    }

    public function testTranslateMethodWithSingleStringShouldReturnTranslatedTextAsStringAndDetectedLanguageAsString()
    {
        $requestParams = [
            'POST',
            'https://www.googleapis.com/language/translate/v2',
            ['query' => 'key=' . self::ACCESS_KEY . '&q=What%27s+your+name%3F&target=pt']
        ];

        $body = '{"data":{"translations":[{"translatedText":"Qual o seu nome?","detectedSourceLanguage":"en"}]}}';

        $this->httpClientMock($requestParams, $body);

        $translatedText = $this->client->translate('What\'s your name?', 'pt', $sourceLanguage);

        $this->assertEquals('Qual o seu nome?', $translatedText);
        $this->assertEquals('en', $sourceLanguage);
    }

    public function testTranslateMethodWithMultipleStringShouldReturnTranslatedTextAsArrayAndDetectedLanguageAsArray()
    {
        $requestParams = [
            'POST',
            'https://www.googleapis.com/language/translate/v2',
            ['query' => 'q%5B0%5D=What%27s+your+name%3F%5D&q%5B1%5D=What+are+you+doing%3F&key=' . self::ACCESS_KEY . '&target=pt']
        ];

        $body = '{"data":{"translations":[{"translatedText":"Qual o seu nome?","detectedSourceLanguage":"en"},{"translatedText":"O que você está fazendo?","detectedSourceLanguage":"en"}]}}';

        $this->httpClientMock($requestParams, $body);

        $translatedText = $this->client->translate(
            ['What\'s your name?]', 'What are you doing?'],
            'pt',
            $sourceLanguage
        );

        $this->assertInternalType('array', $translatedText);
        $this->assertEquals('Qual o seu nome?', $translatedText[0]);
        $this->assertEquals('O que você está fazendo?', $translatedText[1]);

        $this->assertInternalType('array', $sourceLanguage);
        $this->assertEquals('en', $sourceLanguage[0]);
        $this->assertEquals('en', $sourceLanguage[0]);
    }

    public function testTranslateMethodWithSingleStringAndSourceLanguageShouldReturnTranslatedTextAsString()
    {
        $requestParams = [
            'POST',
            'https://www.googleapis.com/language/translate/v2',
            ['query' => 'key=' . self::ACCESS_KEY . '&q=What%27s+your+name%3F&target=pt&source=en']
        ];

        $body = '{"data":{"translations":[{"translatedText":"Qual o seu nome?"}]}}';

        $this->httpClientMock($requestParams, $body);

        $sourceLanguage = 'en';

        $translatedText = $this->client->translate('What\'s your name?', 'pt', $sourceLanguage);

        $this->assertEquals('Qual o seu nome?', $translatedText);
    }

    public function testTranslateMethodWithSingleAndInvalidTargetLanguageShouldReturnTranslationErrorException()
    {
        $this->expectException('\GoogleTranslate\Exception\TranslationErrorException');
        $this->expectExceptionMessage('Translation error: Client error: `POST https://www.googleapis.com/language/translate/v2?key=' . self::ACCESS_KEY . '&q=estou+aqui&target=aa` resulted in a `400 Bad Request` response:');
        $this->expectExceptionCode(4);

        $requestParams = [
            'POST',
            'https://www.googleapis.com/language/translate/v2',
            ['query' => 'key=' . self::ACCESS_KEY . '&q=What%27s+your+name%3F&target=aa']
        ];

        $mockGuzzleException = new TransferException('Client error: `POST https://www.googleapis.com/language/translate/v2?key=' . self::ACCESS_KEY . '&q=estou+aqui&target=aa` resulted in a `400 Bad Request` response:');
        $this->httpClientMock->method('request')
            ->withConsecutive($requestParams)
            ->willThrowException($mockGuzzleException);

        $this->client->translate('What\'s your name?', 'aa', $sourceLanguage);
    }

    /**
     * @expectedException \GoogleTranslate\Exception\TranslationErrorException
     * @expectedExceptionMessage Invalid response
     * @expectedExceptionCode 4
     */
    public function testTranslateMethodWithSingleStringAndSourceLanguageAndMalformedJsonResponseShouldReturnTranslationErrorException()
    {
        $requestParams = [
            'POST',
            'https://www.googleapis.com/language/translate/v2',
            ['query' => 'key=' . self::ACCESS_KEY . '&q=What%27s+your+name%3F&target=pt']
        ];

        $body = '{"data":{}}';

        $this->httpClientMock($requestParams, $body);
        $this->client->translate('What\'s your name?', 'pt', $sourceLanguage);
    }

    /**
     * @expectedException \GoogleTranslate\Exception\InvalidTargetLanguageException
     * @expectedExceptionMessage Invalid target language
     * @expectedExceptionCode 3
     */
    public function testLanguagesMethodWithInvalidTargetLanguageShouldReturnInvalidTargetLanguageException()
    {
        $this->client->languages('12');
    }

    public function testLanguagesMethodWithTargetLanguageShouldReturnLanguagesSupportedCodeAndName()
    {
        $requestParams = [
            'GET',
            'https://www.googleapis.com/language/translate/v2/languages',
            ['query' => 'key=' . self::ACCESS_KEY . '&target=pt-br']
        ];

        $body = '{"data":{"languages":[{"language":"de","name":"Alemão"},{"language":"ga","name":"Irlandês"},{"language":"it","name":"Italiano"},{"language":"ja","name":"Japonês"}]}}';

        $this->httpClientMock($requestParams, $body);

        $languages = $this->client->languages('pt-br');

        $this->assertInternalType('array', $languages);
        $this->assertEquals(4, count($languages));

        $expectedValues = [
            [
                'language' => 'de',
                'name' => 'Alemão'
            ],
            [
                'language' => 'ga',
                'name' => 'Irlandês'
            ],
            [
                'language' => 'it',
                'name' => 'Italiano'
            ],
            [
                'language' => 'ja',
                'name' => 'Japonês'
            ]
        ];

        foreach ($languages as $index => $language) {
            $this->assertArrayHasKey('language', $language);
            $this->assertArrayHasKey('name', $language);
            $this->assertEquals($expectedValues[$index]['language'], $language['language']);
            $this->assertEquals($expectedValues[$index]['name'], $language['name']);
        }
    }

    public function testLanguagesMethodShouldReturnLanguagesSupportedCodes()
    {
        $requestParams = [
            'GET',
            'https://www.googleapis.com/language/translate/v2/languages',
            ['query' => 'key=' . self::ACCESS_KEY . '&target=pt-br']
        ];

        $body = '{"data":{"languages":[{"language":"de"},{"language":"ga"},{"language":"it"},{"language":"ja"}]}}';

        $this->httpClientMock($requestParams, $body);

        $languages = $this->client->languages('pt-br');

        $this->assertInternalType('array', $languages);
        $this->assertEquals(4, count($languages));

        $expectedValues = [
            ['language' => 'de'],
            ['language' => 'ga'],
            ['language' => 'it'],
            ['language' => 'ja']
        ];

        foreach ($languages as $index => $language) {
            $this->assertArrayHasKey('language', $language);
            $this->assertEquals($expectedValues[$index]['language'], $language['language']);
        }
    }

    public function testLanguagesMethodWithInvalidTargetLanguageShouldReturnLanguagesErrorException()
    {
        $this->expectException('\GoogleTranslate\Exception\LanguagesErrorException');
        $this->expectExceptionMessage('Languages error: Client error: `GET https://www.googleapis.com/language/translate/v2/languages?key=' . self::ACCESS_KEY . '&target=aa-aa` resulted in a `400 Bad Request` response:');
        $this->expectExceptionCode(5);

        $requestParams = [
            'GET',
            'https://www.googleapis.com/language/translate/v2/languages',
            ['query' => 'key=' . self::ACCESS_KEY . '&target=aa-aa']
        ];

        $mockGuzzleException = new TransferException('Client error: `GET https://www.googleapis.com/language/translate/v2/languages?key=' . self::ACCESS_KEY . '&target=aa-aa` resulted in a `400 Bad Request` response:');
        $this->httpClientMock->method('request')
            ->withConsecutive($requestParams)
            ->willThrowException($mockGuzzleException);

        $this->client->languages('aa-aa');
    }

    /**
     * @expectedException \GoogleTranslate\Exception\LanguagesErrorException
     * @expectedExceptionMessage Invalid response
     * @expectedExceptionCode 5
     */
    public function testLanguagesMethodWithMalformedJsonResponseShouldReturnLanguagesErrorException()
    {
        $requestParams = [
            'GET',
            'https://www.googleapis.com/language/translate/v2/languages',
            ['query' => 'key=' . self::ACCESS_KEY]
        ];

        $body = '{"data":{}}';

        $this->httpClientMock($requestParams, $body);
        $this->client->languages();
    }

    public function httpClientMock($requestParams, $body)
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getBody')
            ->willReturn($body);

        $this->httpClientMock->method('request')
            ->withConsecutive($requestParams)
            ->willReturn($responseMock);
    }
}
