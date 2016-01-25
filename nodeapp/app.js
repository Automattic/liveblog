var program = require('commander');

program
  .option('--socketio-port [port]', 'Set Socket.io server port (default: 3000)', 3000)
  .option('--redis-host [host]', 'Set Redis server host (default: localhost)', 'localhost')
  .option('--redis-port [port]', 'Set Redis server port (default: 6379)', 6379)
  .parse(process.argv);

var redis = require('socket.io-redis');
var io = require('socket.io')(program.socketioPort);

io.adapter(redis({ host: program.redisHost, port: program.redisPort }));

// Handle opened socket connections
io.on('connection', function(socket) {});
