# WebSocket support

By default Liveblog uses AJAX polling to update the list of entries, which means there is a delay of a few seconds between an entry being created and being shown to readers. For closer to real-time delivery you can configure Liveblog to use WebSockets instead, via [Socket.IO](https://socket.io), [Redis](https://redis.io) and [socket.io-php-emitter](https://github.com/rase-/socket.io-php-emitter).

The Socket.IO server cannot tell whether a client is an authenticated WordPress user or what their capabilities are, so WebSockets are used only for **public** liveblog posts.

## Requirements

* [Node.js](https://nodejs.org/) — to run the Socket.IO server.
* [Redis](https://redis.io) — for sending messages from WordPress to the Socket.IO server.
* [Composer](https://getcomposer.org/) — to install [socket.io-php-emitter](https://github.com/rase-/socket.io-php-emitter) and [Predis](https://github.com/nrk/predis).

## Install dependencies

From the Liveblog plugin directory, install the PHP-side dependencies:

```bash
composer install
```

The Node.js Socket.IO server lives in its own repository: [Automattic/liveblog-sockets-app](https://github.com/Automattic/liveblog-sockets-app). Follow that repository's instructions to install and run it.

## Configuration

Add the following constants to `wp-config.php`:

| Constant | Purpose | Default |
|----------|---------|---------|
| `LIVEBLOG_USE_SOCKETIO` | Set to `true` to enable WebSocket support. | `false` |
| `LIVEBLOG_SOCKETIO_URL` | URL the Socket.IO client uses to connect to the server. | `YOUR_SITE_DOMAIN:3000` |
| `LIVEBLOG_REDIS_HOST` | Redis server host. | `localhost` |
| `LIVEBLOG_REDIS_PORT` | Redis server port. | `6379` |

## Verifying it works

Open a liveblog post in two browser windows. Whenever a new entry is added in one window, the list in the other window should refresh in close to real time. The browser dev tools network tab should show a single WebSocket connection rather than periodic AJAX polls.

## Private liveblog posts

When using WebSockets, the plugin generates a unique key for each liveblog post (based on the post ID and its status). The key is shared with users who have permission to view the post when the page loads. The browser sends that key to the Socket.IO server when establishing a connection, and the server only sends entries to clients with the right key.

This is enough to prevent unauthorised users from receiving Liveblog entries, but it has a limitation: once a user has the post key, if they save it elsewhere they can keep receiving messages even if their access to the post is later revoked.

If you use private liveblog posts to share sensitive data and need to invalidate keys when access is revoked, use the `liveblog_socketio_post_key` filter to implement your own key generation. For example, generate a random key per post, store it as post meta, and let an editor invalidate it manually.

## Debugging tips

If WebSockets aren't refreshing the entry list, check:

* The browser dev tools network tab — was a WebSocket connection established? Does the console show errors?
* That the Node.js Socket.IO server is running. It refuses to start if it can't connect to Redis.
* The plugin falls back to AJAX polling if it can't connect to Redis.
* `redis-cli MONITOR` shows all messages received by Redis.
* Start the Node.js app with `DEBUG=socket.io* node app.js` to see Socket.IO debug output.
