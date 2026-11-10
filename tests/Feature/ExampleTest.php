<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Khách truy cập trang chủ sẽ được chuyển hướng tới trang đăng nhập.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    /**
     * Trang đăng nhập hiển thị thành công (HTTP 200).
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
