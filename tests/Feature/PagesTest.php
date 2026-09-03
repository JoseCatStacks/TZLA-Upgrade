<?php

namespace Tests\Feature;

use Tests\TestCase;

class PagesTest extends TestCase
{
    public function test_landing_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('TZLA');
    }

    public function test_staking_page_injects_recovered_config(): void
    {
        $this->get('/staking')
            ->assertOk()
            ->assertSee('3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ', false)
            ->assertSee('TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3', false)
            ->assertSee('api\/rpc', false);
    }

    public function test_staking_portal_tab_redirects_to_vault(): void
    {
        $this->get('/portal/staking')->assertRedirect(route('staking'));
    }

    public function test_unknown_portal_tab_is_404(): void
    {
        $this->get('/portal/not-a-real-tab')->assertNotFound();
    }

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
