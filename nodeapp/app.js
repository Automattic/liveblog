var redis = require('socket.io-redis');
var io = require('socket.io')(3000);

io.adapter(redis());

// Handle opened socket connections
io.on('connection', function(socket) {});
