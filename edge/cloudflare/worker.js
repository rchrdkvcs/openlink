export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const host = url.hostname;
    url.hostname = env.ORIGIN_HOST;

    const headers = new Headers(request.headers);
    headers.set('X-Openlink-Host', host);
    headers.set('X-Openlink-Client-Ip', request.headers.get('CF-Connecting-IP') ?? '');
    headers.set('X-Openlink-Country', request.cf?.country ?? '');
    headers.set('X-Openlink-Edge-Secret', env.EDGE_SECRET);

    return fetch(url, {
      method: request.method,
      headers,
      body: request.body,
      redirect: 'manual',
    });
  },
};
