# Use Redis for cache, rate limits, and queues

Openlink uses Redis from the MVP for short link resolution caching, public rate limiting, and Laravel queues for asynchronous analytics and background tasks. Redirection performance is central to the product, so Redis is part of the standard runtime instead of being deferred.
