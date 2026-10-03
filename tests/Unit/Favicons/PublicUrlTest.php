<?php

namespace Tests\Unit\Favicons;

use App\Services\Favicons\PublicUrl;
use PHPUnit\Framework\TestCase;

class PublicUrlTest extends TestCase
{
    public function test_only_public_http_hosts_are_allowed(): void
    {
        $urls = new PublicUrl;

        $this->assertTrue($urls->isAllowed('https://93.184.216.34/page'));
        $this->assertFalse($urls->isAllowed('ftp://93.184.216.34/page'));
        $this->assertFalse($urls->isAllowed('http://127.0.0.1/'));
        $this->assertFalse($urls->isAllowed('http://10.0.0.4/'));
        $this->assertFalse($urls->isAllowed('http://printer.local/'));
        $this->assertFalse($urls->isAllowed('http://localhost/'));
    }

    public function test_origin_keeps_explicit_ports_and_normalises_case(): void
    {
        $urls = new PublicUrl;

        $this->assertSame('https://93.184.216.34', $urls->origin('HTTPS://93.184.216.34/a?b=c'));
        $this->assertSame('https://93.184.216.34:8443', $urls->origin('https://93.184.216.34:8443/a'));
        $this->assertNull($urls->origin('http://127.0.0.1/a'));
    }

    public function test_relative_references_resolve_against_the_base_url(): void
    {
        $urls = new PublicUrl;
        $base = 'https://93.184.216.34/blog/post';

        $this->assertSame('https://93.184.216.34/icon.png', $urls->absolute($base, '/icon.png'));
        $this->assertSame('https://cdn.example.com/icon.png', $urls->absolute($base, '//cdn.example.com/icon.png'));
        $this->assertSame('https://93.184.216.34/blog/icon.png', $urls->absolute($base, 'icon.png'));
        $this->assertSame('https://other.example.com/i.png', $urls->absolute($base, 'https://other.example.com/i.png'));
        $this->assertNull($urls->absolute($base, 'data:image/png;base64,AAAA'));
    }

    public function test_redirects_to_private_hosts_are_refused(): void
    {
        $urls = new PublicUrl;

        $this->assertSame('https://93.184.216.34/next', $urls->redirectTarget('https://93.184.216.34/start', '/next'));
        $this->assertNull($urls->redirectTarget('https://93.184.216.34/start', 'http://127.0.0.1/'));
        $this->assertNull($urls->redirectTarget('https://93.184.216.34/start', null));
    }
}
