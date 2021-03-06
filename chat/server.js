'use strict'

const http = require('http')
const { parse: parseCookie } = require('cookie')
const { readFile } = require('fs')
const { URL } = require('url')
const connectSocketIO = require('socket.io')
const redis = require('redis')

const inputPort = 1338
const chatPort = 1337
const listenHost = process.argv[2] || '127.0.0.1'

const redisClient = redis.createClient({
  host: process.env.REDIS_HOST || '127.0.0.1',
  port: process.env.REDIS_PORT || 6379
})

const sessionIdsScriptFilename = `${__dirname}/session-ids.lua`
let sessionIdsScriptSHA

const connectedClients = {}
let numRegistrations = 0
let numConnections = 0

const sendToUser = (userId, channel, method, payload) => {
  fetchSessionIdsForUser(userId, (err, sessionIds) => {
    if (err) return console.error('could not get session ids for', userId, err)
    for (const sessionId of sessionIds) {
      sendToSession(sessionId, channel, method, payload)
    }
  })
}

const sendToSession = (sessionId, channel, method, payload) => {
  for (const connection of connectionsForSession(sessionId)) {
    connection.emit(channel, { m: method, o: payload })
  }
}

const loadSessionIdsScript = callback => {
  readFile(sessionIdsScriptFilename, 'utf8', (err, contents) => {
    if (err) return callback(err)
    redisClient.script('LOAD', contents, (err, sha) => {
      if (err) return callback(err)
      console.log('loaded session ids script', sha)
      sessionIdsScriptSHA = sha
      callback(null, sha)
    })
  })
}

const getSessionIdsScriptSHA = callback => {
  if (sessionIdsScriptSHA) {
    callback(null, sessionIdsScriptSHA)
  } else {
    loadSessionIdsScript(err => {
      callback(err, sessionIdsScriptSHA)
    })
  }
}

const fetchSessionIdsForUser = (userId, callback) => {
  getSessionIdsScriptSHA((err, sha) => {
    if (err) return callback(err)
    redisClient.evalsha(sha, 0, userId, (err, res) => {
      if (err && err.code === 'NOSCRIPT') {
        sessionIdsScriptSHA = null
        loadSessionIdsScript(err => {
          if (err) return callback(err)
          fetchSessionIdsForUser(userId, callback)
        })
      } else {
        callback(err, res)
      }
    })
  })
}

const connectionsForSession = (sessionId) => {
  if (connectedClients[sessionId]) {
    return connectedClients[sessionId]
  } else {
    return []
  }
}

const parseRequestURL = (req) => {
  // url.parse is being deprecated, but URL does not accept relative URLS so we specify a dummy base
  // see https://github.com/nodejs/node/issues/12682
  return new URL(req.url, 'http://localhost')
}

const inputServer = http.createServer((req, res) => {
  const url = parseRequestURL(req)
  if (url.pathname === '/stats') {
    res.writeHead(200)
    res.end(JSON.stringify({
      connections: numConnections,
      registrations: numRegistrations,
      sessions: Object.keys(connectedClients).length
    }))
    return
  }

  const sessionId = url.searchParams.get('c')
  const app = url.searchParams.get('a')
  const method = url.searchParams.get('m')
  const options = url.searchParams.get('o')
  const userId = url.searchParams.get('u')
  const userIds = url.searchParams.get('us')

  if (sessionId) {
    sendToSession(sessionId, app, method, options)
  }

  if (userId) {
    sendToUser(userId, app, method, options)
  }

  if (userIds) {
    for (const userId of userIds.split(',')) {
      sendToUser(userId, app, method, options)
    }
  }

  res.writeHead(200)
  res.end('\n')
})

const chatServer = http.createServer((req, res) => {
  res.writeHead(200)
  res.end('\n')
})
const io = connectSocketIO(chatServer)

io.use((socket, next) => {
  const cookieVal = socket.request.headers.cookie
  if (cookieVal) {
    const cookie = parseCookie(cookieVal)
    socket.sid = cookie.PHPSESSID || cookie.sessionid
    if (socket.sid) return next()
  }
  next(new Error('not authorized'))
})

io.on('connection', (socket) => {
  const sessionId = socket.sid
  numConnections++
  socket.on('register', () => {
    numRegistrations++
    if (!connectedClients[sessionId]) connectedClients[sessionId] = []
    connectedClients[sessionId].push(socket)
  })

  socket.on('disconnect', () => {
    numConnections--
    const connections = connectedClients[sessionId]
    if (sessionId && connections) {
      if (connections.includes(socket)) {
        connections.splice(connections.indexOf(socket), 1)
        numRegistrations--
      }
      if (connections.length === 0) {
        delete connectedClients[sessionId]
      }
    }
  })
})

loadSessionIdsScript((err, sha) => {
  if (err) return console.error('failed to load session ids script', err)
})

inputServer.listen(inputPort, listenHost, () => {
  console.log('http server started on', `${listenHost}:${inputPort}`)
})

chatServer.listen(chatPort, listenHost, () => {
  console.log('socket.io started on port', `${listenHost}:${chatPort}`)
})

module.exports = {
  inputServer: inputServer,
  chatServer: chatServer
}
