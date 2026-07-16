<?php
/*
|==================================================================
| FITUR: Uji Security Headers
| Membuktikan header keamanan OWASP hadir pada respons API.
|==================================================================
*/

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_present(): void
    {
        $res = $this->getJson('/api/settings');

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'no-referrer');
    }
}
