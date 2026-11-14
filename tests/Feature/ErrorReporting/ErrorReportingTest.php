<?php

namespace Tests\Feature\ErrorReporting;

use App\Services\ErrorReporting\BrowserErrorReporting;
use App\Services\ErrorReporting\TracesSampler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Sentry\ClientBuilder;
use Sentry\DataCollection\HttpMessageType;
use Sentry\State\HubInterface;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\TransactionContext;
use Tests\TestCase;

class ErrorReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sentry.traces_sample_rate' => 0.25]);
    }

    public function test_nothing_is_reported_without_a_dsn(): void
    {
        config(['sentry.dsn' => null, 'services.sentry.browser_dsn' => null]);

        $this->assertNull(app(HubInterface::class)->getClient()?->getOptions()->getDsn());

        $this->get('http://localhost/login')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('errorReporting', null));
    }

    public function test_server_events_never_collect_visitor_data(): void
    {
        config(['sentry.dsn' => 'https://key@errors.example.test/1']);

        $dataCollection = app(ClientBuilder::class)->getOptions()->getDataCollection();

        $this->assertFalse($dataCollection->shouldCollectUserInfo());
        $this->assertTrue($dataCollection->getCookies()->isOff());
        $this->assertTrue($dataCollection->getUrlQueryParams()->isOff());
        $this->assertTrue($dataCollection->getHttpHeaders()['response']->isOff());
        $this->assertNotContains('user-agent', $dataCollection->getHttpHeaders()['request']->getTerms());
        $this->assertFalse($dataCollection->shouldCollectHttpBody(HttpMessageType::incomingRequest()));
    }

    public function test_dashboard_pages_receive_the_browser_configuration(): void
    {
        config([
            'services.sentry.browser_dsn' => 'https://browser@errors.example.test/2',
            'services.sentry.browser_traces_sample_rate' => 0.1,
            'sentry.environment' => 'cloud',
            'sentry.release' => 'v1.2.0',
        ]);

        $this->get('http://localhost/login')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('errorReporting', [
                'dsn' => 'https://browser@errors.example.test/2',
                'environment' => 'cloud',
                'release' => 'v1.2.0',
                'tracesSampleRate' => 0.1,
            ]));
    }

    public function test_visitor_facing_pages_never_receive_the_browser_configuration(): void
    {
        config(['services.sentry.browser_dsn' => 'https://browser@errors.example.test/2']);

        $reporting = app(BrowserErrorReporting::class);

        $this->assertNull($reporting->forRequest(Request::create('http://go.example.test/promo')));
        $this->assertNull($reporting->forRequest($this->routedRequest('http://localhost/p/team')));
        $this->assertNotNull($reporting->forRequest($this->routedRequest('http://localhost/login')));
    }

    public function test_public_resolution_is_never_traced(): void
    {
        $sampler = app(TracesSampler::class);

        $this->assertSame(0.0, $sampler->rateForRequest(Request::create('http://go.example.test/promo')));
        $this->assertSame(0.0, $sampler->rateForRequest(Request::create('http://localhost/p/team')));
        $this->assertSame(0.0, $sampler->rateForRequest(Request::create('http://localhost/qr/abc')));
        $this->assertSame(0.25, $sampler->rateForRequest(Request::create('http://localhost/login')));
    }

    public function test_background_transactions_use_the_configured_rate(): void
    {
        $context = new TransactionContext;
        $context->setOp('queue.process');

        $this->assertSame(0.25, TracesSampler::sample(SamplingContext::getDefault($context)));
    }

    public function test_tracing_stays_disabled_without_a_sample_rate(): void
    {
        $this->assertNull((require config_path('sentry.php'))['traces_sampler']);
    }

    private function routedRequest(string $url): Request
    {
        $request = Request::create($url);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return $request;
    }
}
