<?php

namespace Reem\Tests\Unit\Component\CCM\Administrator\Model;

use Joomla\Tests\Unit\UnitTestCase;
use Joomla\CMS\Http\Http;

use Reem\Component\CCM\Administrator\Model\MigrationModel;

class MigrationModelTest extends UnitTestCase
{
    /**
     * Test getSourceItems returns expected data
     */
    public function testGetSourceItems()
    {
        $httpMock = $this->createMock(Http::class);
        $httpMock->method('get')->willReturn(
            (object)[
                'body' => json_encode([
                    [
                        'ID' => 13,
                        'title' => 'Article 1 WordPress',
                        'content' => '<p>Content 1 WordPress</p>',
                        'slug' => 'article-1-wordpress',
                        'status' => 'publish',
                        'date' => '2025-05-26T14:59:39+03:00',
                    ]
                ]),
                'code' => 200,
                'headers' => ['Content-Type' => 'application/json'],
            ]
        );

        $model = $this->getMockBuilder(MigrationModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $reflection = new \ReflectionClass($model);

        $property = $reflection->getProperty('http');
        $property->setAccessible(true);
        $property->setValue($model, $httpMock);

        $method     = $reflection->getMethod('getSourceItems');
        $method->setAccessible(true);

        $sourceCms  = (object)[ 'name' => 'wordpress', 'url' => 'https://example.com/api' ];
        $sourceType = 'posts';

        $sourceItems = $method->invokeArgs($model, [$sourceCms, $sourceType]);
        $this->assertIsArray($sourceItems);
        $this->assertCount(1, $sourceItems);
        $this->assertEquals('Article 1 WordPress', $sourceItems[0]['title']);
        $this->assertEquals('Content 1 WordPress', strip_tags($sourceItems[0]['content']));
        $this->assertEquals('article-1-wordpress', $sourceItems[0]['slug']);
        $this->assertEquals('publish', $sourceItems[0]['status']);
    }


    /**
     * Test convertSourceCmsToCcm mapping logic
     */
    public function testConvertSourceCmsToCcm()
    {
        $model = $this->getMockBuilder(MigrationModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $sourceCms  = (object)[ 'name' => 'wordpress' ];
        $sourceType = 'posts';

        $sourceItems = [
            [
                'ID' => 13,
                'title' => 'Article 1 WordPress',
                'content' => '<p>Content 1 WordPress</p>',
                'slug' => 'article-1-wordpress',
                'status' => 'publish',
                'date' => '2025-05-26T14:59:39+03:00',
            ]
        ];

        $reflection = new \ReflectionClass($model);
        $method     = $reflection->getMethod('convertSourceCmsToCcm');
        $method->setAccessible(true);

        $ccmItems = $method->invokeArgs($model, [$sourceCms, $sourceItems, $sourceType]);

        $this->assertIsArray($ccmItems);
        $this->assertEquals('Article 1 WordPress', $ccmItems[0]['title']);
        $this->assertEquals('article-1-wordpress', $ccmItems[0]['alias']);
        $this->assertEquals('publish', $ccmItems[0]['status']);
    }

    /**
     * Test convertSourceCmsToCcm throws if no mapping exists
     */
    public function testConvertSourceCmsToCcmEmptyForWrongInput()
    {
        $model = $this->getMockBuilder(MigrationModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $sourceCms   = (object)[ 'name' => 'wordpress' ];
        $sourceType  = 'not_existing_type';
        $sourceItems = [
            ['ID' => 1, 'title' => 'Test']
        ];

        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('convertSourceCmsToCcm');
        $method->setAccessible(true);

        $ccmItems = $method->invokeArgs($model, [$sourceCms, $sourceItems, $sourceType]);
        $this->assertEquals([[]], $ccmItems);
    }

    /**
     * Test convertCcmToTargetCms mapping logic
     */
    public function testConvertCcmToTargetCms()
    {
        $model = $this->getMockBuilder(MigrationModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $targetCms  = (object)[ 'name' => 'joomla' ];
        $targetType = 'articles';

        $ccmItems = [
            [
                'id' => 13,
                'title' => 'Article 1 WordPress',
                'alias' => 'article-1-wordpress',
                'context' => '<p>Content 1 WordPress</p>',
                'status' => 'publish',
                'created' => '2025-05-26T14:59:39+03:00',
            ]
        ];

        $reflection = new \ReflectionClass($model);
        $method     = $reflection->getMethod('convertCcmToTargetCms');
        $method->setAccessible(true);

        $targetItems = $method->invokeArgs($model, [$ccmItems, $targetCms, $targetType]);

        $this->assertIsArray($targetItems);
        $this->assertEquals('Article 1 WordPress', $targetItems[0]['title']);
        $this->assertEquals('article-1-wordpress', $targetItems[0]['alias']);
        $this->assertEquals('1', $targetItems[0]['state']);
    }

    /**
     * Test formatDate returns formatted date
     */
    public function testFormatDateReturnsFormatted()
    {
        $model = $this->getMockBuilder(MigrationModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $reflection = new \ReflectionClass($model);
        $method     = $reflection->getMethod('formatDate');
        $method->setAccessible(true);

        $this->assertEquals('2025-06-25', $method->invokeArgs($model, ['2025-06-25T14:59:39+03:00', 'Y-m-d']));
    }

    /**
     * Test migrateItemsToTargetCms method
     */
    public function testMigrateItemsToTargetCms()
    {
        $httpMock = $this->createMock(Http::class);
        $httpMock->method('post')->willReturn(
            (object)[
                'code' => 201,
                'body' => json_encode(['id' => 42]),
                'headers' => ['Content-Type' => 'application/json'],
            ]
        );

        $model = $this->getMockBuilder(MigrationModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // $GLOBALS['file_put_contents_called'] = false;
        // $filePutContentsMock = function($filename, $data) {
        //     $GLOBALS['file_put_contents_called'] = true;
        //     return true;
        // };

        $targetCms = (object)[
            'name' => 'joomla',
            'url' => 'https://example.com/api',
            'credentials' => 'token123'
        ];
        $targetType = 'articles';
        $ccmToTargetItems = [
            ['id' => 13, 'title' => 'Article 1 WordPress']
        ];

        $reflection = new \ReflectionClass($model);

        $property = $reflection->getProperty('http');
        $property->setAccessible(true);
        $property->setValue($model, $httpMock);

        $propertyFile = $reflection->getProperty('migrationIdMapFile');
        $propertyFile->setAccessible(true);
        $propertyFile->setValue($model, sys_get_temp_dir() . '/migrationIdMap.json');

        $method = $reflection->getMethod('migrateItemsToTargetCms');
        $method->setAccessible(true);

        $result = $method->invokeArgs($model, [$targetCms, $targetType, $ccmToTargetItems]);

        $this->assertTrue($result);
    }
}