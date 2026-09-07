<?php
/**
 * Base Resource Test Case
 */

namespace Tests\Unit\Resources;

use App\Http\Resources\Base\BaseResource;
use Tests\TestCase;

class BaseResourceTest extends TestCase
{
    public function test_base_resource_returns_common_fields(): void
    {
        $resource = new class extends BaseResource {
            protected function addCommonFields(): void
            {
                $this->addCommonFields([
                    'id' => 1,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-01T00:00:00Z',
                    'data' => [
                        'test' => 'value',
                    ],
                ]);
            }
        };

        $data = $resource->getData();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('value', $data['data']['test']);
    }

    public function test_base_resource_returns_minimal_data(): void
    {
        $resource = new class extends BaseResource {
            protected function addMinimalFields(): void
            {
                $this->addCommonFields([
                    'id' => 1,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-01T00:00:00Z',
                    'data' => [
                        'minimal' => 'data',
                    ],
                ]);
            }
        };

        $data = $resource->getMinimalData();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('data', $data['data']['minimal']);
    }
}