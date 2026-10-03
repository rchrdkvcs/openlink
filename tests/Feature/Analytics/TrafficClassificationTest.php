<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\ReferrerClassifier;
use App\Services\Analytics\UserAgentParser;
use Tests\Support\UserAgents;
use Tests\TestCase;

class TrafficClassificationTest extends TestCase
{
    public function test_user_agent_parser_covers_common_agents(): void
    {
        $parser = new UserAgentParser;

        $chrome = $parser->parse(UserAgents::CHROME_ANDROID);
        $this->assertSame(['browser' => 'Chrome', 'os' => 'Android', 'device_type' => 'mobile', 'is_bot' => false], $chrome);

        $safari = $parser->parse(UserAgents::SAFARI_MAC);
        $this->assertSame(['browser' => 'Safari', 'os' => 'macOS', 'device_type' => 'desktop', 'is_bot' => false], $safari);

        $bot = $parser->parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->assertTrue($bot['is_bot']);
        $this->assertSame('bot', $bot['device_type']);

        $curl = $parser->parse('curl/8.5.0');
        $this->assertTrue($curl['is_bot']);
    }

    public function test_referrer_classifier_maps_channels(): void
    {
        $classifier = new ReferrerClassifier;

        $this->assertSame(['host' => null, 'channel' => 'direct'], $classifier->classify(null));
        $this->assertSame(['host' => 'google.com', 'channel' => 'search'], $classifier->classify('https://www.google.com/search?q=x'));
        $this->assertSame(['host' => 't.co', 'channel' => 'social'], $classifier->classify('https://t.co/abc'));
        $this->assertSame(['host' => 'youtube.com', 'channel' => 'video'], $classifier->classify('https://www.youtube.com/watch?v=1'));
        $this->assertSame(['host' => 'chatgpt.com', 'channel' => 'ai'], $classifier->classify('https://chatgpt.com/'));
        $this->assertSame(['host' => 'example.org', 'channel' => 'referral'], $classifier->classify('https://example.org/blog'));
    }
}
