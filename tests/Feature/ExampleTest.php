<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    #[Test]
    public function the_root_url_leads_to_the_filament_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    #[Test]
    public function the_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
