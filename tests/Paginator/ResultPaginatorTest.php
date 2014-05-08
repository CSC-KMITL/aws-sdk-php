<?php
namespace Aws\Test\Paginator;

use Aws\Result;
use Aws\Test\UsesServiceClientTrait;

/**
 * @covers Aws\Paginator\ResultPaginator
 */
class ResultPaginatorTest extends \PHPUnit_Framework_TestCase
{
    use UsesServiceClientTrait;

    /**
     * @dataProvider getPaginatorIterationData
     */
    public function testStandardIterationWorkflow(
        array $config,
        array $results,
        $expectedRequestCount,
        array $expectedTableNames
    ) {
        $requestCount = 0;

        // Create the client and paginator
        $client = $this->getTestClient('dynamodb');
        $this->addMockResults($client, $results);
        $paginator = $client->getPaginator('ListTables', [], $config + [
            'process' => function () use (&$requestCount) {$requestCount++;}
        ]);

        // Iterate over the paginator and keep track of the keys and values
        $tableNames = [];
        foreach ($paginator as $result) {
            $tableNames = array_merge($tableNames, $result['TableNames']);
        }

        // Make sure the paginator yields the expected results
        $this->assertInstanceOf('Aws\\Result', $result);
        $this->assertEquals($expectedRequestCount, $requestCount);
        $this->assertEquals($expectedTableNames, $tableNames);
    }

    public function testNonIteratorMethods()
    {
        // Get test data
        $config = $this->getPaginatorIterationData()[0][0];
        $results = $this->getPaginatorIterationData()[0][1];

        // Create the client and paginator
        $client = $this->getTestClient('dynamodb');
        $this->addMockResults($client, $results);
        $paginator = $client->getPaginator('ListTables', [], $config);

        // Test getConfig, getNext, and getNextToken methods
        $this->assertEquals('Limit', $paginator->getConfig('limit_key'));
        $this->assertContains('Limit', $paginator->getConfig());
        $this->assertEquals(['test1', 'test2'], $paginator->getNext()['TableNames']);
        $this->assertEquals('test2', $paginator->getNextToken());
        $this->assertEquals([], $paginator->getNext()['TableNames']);
        $this->assertEquals('test2', $paginator->getNextToken());
        $this->assertEquals(['test3'], $paginator->getNext()['TableNames']);
        $this->assertNull($paginator->getNextToken());
    }

    /**
     * @return array Test data
     */
    public function getPaginatorIterationData()
    {
        return [
            // Single field token case
            [
                // Config
                ['input_token' => 'NextToken', 'output_token' => 'LastToken'],
                // Results
                [
                    new Result(['LastToken' => 'test2', 'TableNames' => ['test1', 'test2']]),
                    new Result(['LastToken' => 'test2', 'TableNames' => []]),
                    new Result(['TableNames' => ['test3']]),
                ],
                // Request count
                3,
                // Table names
                ['test1', 'test2', 'test3'],
            ],
            [
                // Config
                ['input_token' => ['NT1', 'NT2'], 'output_token' => ['LT1', 'LT2']],
                // Results
                [
                    new Result(['LT1' => 'foo', 'LT2' => 'bar', 'TableNames' => ['test1', 'test2']]),
                    new Result(['LT1' => 'foo', 'LT2' => 'bar', 'TableNames' => []]),
                    new Result(['TableNames' => ['test3']]),
                ],
                // Request count
                3,
                // Table names
                ['test1', 'test2', 'test3'],
            ]
        ];
    }
}