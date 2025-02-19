<?php

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;

class QueueDatabaseQueueIntegrationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var DatabaseQueue The queue instance.
     */
    protected $queue;

    /**
     * @var string The jobs table name.
     */
    protected $table;

    /**
     * @var Container The IOC container.
     */
    protected $container;

    public function setUp()
    {
        $db = new DB;

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->bootEloquent();

        $db->setAsGlobal();

        $this->table = 'jobs';

        $this->queue = new DatabaseQueue($this->connection(), $this->table);

        $this->container = $this->getMock(Container::class);

        $this->queue->setContainer($this->container);

        $this->createSchema();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create($this->table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->tinyInteger('attempts')->unsigned();
            $table->tinyInteger('reserved')->unsigned();
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue', 'reserved', 'reserved_at']);
        });
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return Illuminate\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->schema()->drop('jobs');
    }

    /**
     * Test that jobs that are not reserved and have an available_at value less then now, are popped.
     */
    public function testAvailableAndUnReservedJobsArePopped()
    {
        $this->connection()
            ->table('jobs')
            ->insert([
                'id' => 1,
                'queue' => $mock_queue_name = 'mock_queue_name',
                'payload' => 'mock_payload',
                'attempts' => 0,
                'reserved' => 0,
                'reserved_at' => null,
                'available_at' => Carbon::now()->subSeconds(1)->getTimestamp(),
                'created_at' => Carbon::now()->getTimestamp(),
            ]);

        $popped_job = $this->queue->pop($mock_queue_name);

        $this->assertNotNull($popped_job);
    }

    /**
     * Test that when jobs are popped, the attempts attribute is incremented.
     */
    public function testPoppedJobsIncrementAttempts()
    {
        $job = [
            'id' => 1,
            'queue' => 'mock_queue_name',
            'payload' => 'mock_payload',
            'attempts' => 0,
            'reserved' => 0,
            'reserved_at' => null,
            'available_at' => Carbon::now()->subSeconds(1)->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ];

        $this->connection()->table('jobs')->insert($job);

        $popped_job = $this->queue->pop($job['queue']);

        $database_record = $this->connection()->table('jobs')->find($job['id']);

        $this->assertEquals(1, $database_record->attempts, 'Job attempts not updated in the database!');
        $this->assertEquals(1, $popped_job->attempts(), 'The "attempts" attribute of the Job object was not updated by pop!');
    }

    /**
     * Test that jobs that are not reserved and have an available_at value in the future, are not popped.
     */
    public function testUnavailableJobsAreNotPopped()
    {
        $this->connection()
            ->table('jobs')
            ->insert([
                'id' => 1,
                'queue' => $mock_queue_name = 'mock_queue_name',
                'payload' => 'mock_payload',
                'attempts' => 0,
                'reserved' => 0,
                'reserved_at' => null,
                'available_at' => Carbon::now()->addSeconds(60)->getTimestamp(),
                'created_at' => Carbon::now()->getTimestamp(),
            ]);

        $popped_job = $this->queue->pop($mock_queue_name);

        $this->assertNull($popped_job);
    }

    /**
     * Test that jobs that are reserved and have expired are popped.
     */
    public function testThatReservedAndExpiredJobsArePopped()
    {
        $this->connection()
            ->table('jobs')
            ->insert([
                'id' => 1,
                'queue' => $mock_queue_name = 'mock_queue_name',
                'payload' => 'mock_payload',
                'attempts' => 0,
                'reserved' => 1,
                'reserved_at' => Carbon::now()->subDay()->getTimestamp(),
                'available_at' => Carbon::now()->addDay()->getTimestamp(),
                'created_at' => Carbon::now()->getTimestamp(),
            ]);

        $popped_job = $this->queue->pop($mock_queue_name);

        $this->assertNotNull($popped_job);
    }

    /**
     * Test that jobs that are reserved and not expired and available are not popped.
     */
    public function testThatReservedJobsAreNotPopped()
    {
        $this->connection()
            ->table('jobs')
            ->insert([
                'id' => 1,
                'queue' => $mock_queue_name = 'mock_queue_name',
                'payload' => 'mock_payload',
                'attempts' => 0,
                'reserved' => 1,
                'reserved_at' => Carbon::now()->addDay()->getTimestamp(),
                'available_at' => Carbon::now()->subDay()->getTimestamp(),
                'created_at' => Carbon::now()->getTimestamp(),
            ]);

        $popped_job = $this->queue->pop($mock_queue_name);

        $this->assertNull($popped_job);
    }
}
