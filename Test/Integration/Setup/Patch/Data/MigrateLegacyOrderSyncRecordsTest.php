<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\AnalyticsOrderSync\Test\Integration\Setup\Patch\Data;

use Klevu\AnalyticsOrderSync\Cron\MigrateLegacyOrderSyncRecords as MigrateLegacyOrderSyncRecordsCron;
use Klevu\AnalyticsOrderSync\Setup\Patch\Data\MigrateLegacyOrderSyncRecords;
use Klevu\AnalyticsOrderSync\Test\Fixtures\Order\OrderTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Cron\Model\ResourceModel\Schedule\Collection as CronScheduleCollection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronScheduleCollectionFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @method MigrateLegacyOrderSyncRecords instantiateTestObject(?array $arguments = null)
 * @method MigrateLegacyOrderSyncRecords instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoDbIsolation disabled
 */
class MigrateLegacyOrderSyncRecordsTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use OrderTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var CronScheduleCollectionFactory|null
     */
    private ?CronScheduleCollectionFactory $cronScheduleCollectionFactory = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = MigrateLegacyOrderSyncRecords::class;
        $this->interfaceFqcn = DataPatchInterface::class;

        $this->cronScheduleCollectionFactory = $this->objectManager->get(CronScheduleCollectionFactory::class);
    }

    public function testApply(): void
    {
        $initialCronScheduleCollection = $this->getCronScheduleCollection();
        $this->assertSame(
            expected: 0,
            actual: $initialCronScheduleCollection->getSize(),
            message: 'Initial Cron Schedule count',
        );

        $migrateLegacyOrderSyncRecords = $this->instantiateTestObject();
        $migrateLegacyOrderSyncRecords->apply();

        $finalCronScheduleCollection = $this->getCronScheduleCollection();
        $this->assertSame(
            expected: 1,
            actual: $finalCronScheduleCollection->getSize(),
            message: 'Final Cron Schedule count',
        );

        $cronSchedule = $finalCronScheduleCollection->getFirstItem();
        $scheduledAtUnixtime = strtotime($cronSchedule->getScheduledAt());
        $this->assertGreaterThanOrEqual(
            time(),
            actual: $scheduledAtUnixtime,
        );
        $this->assertLessThanOrEqual(
            time() + 300,
            actual: $scheduledAtUnixtime,
        );
    }

    /**
     * @return CronScheduleCollection
     */
    private function getCronScheduleCollection(): CronScheduleCollection
    {
        $cronScheduleCollection = $this->cronScheduleCollectionFactory->create();
        $cronScheduleCollection->addFieldToFilter(
            field: 'job_code',
            condition: ['eq' => MigrateLegacyOrderSyncRecordsCron::JOB_CODE],
        );
        $cronScheduleCollection->addFieldToFilter(
            field: 'status',
            condition: ['eq' => 'pending'],
        );
        $cronScheduleCollection->addFieldToFilter(
            field: 'created_at',
            condition: [
                'gt' => date('Y-m-d H:i:s', time() - 30),
            ],
        );

        return $cronScheduleCollection;
    }
}
