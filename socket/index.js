const express = require('express');
const { createServer } = require('http');
const { join } = require('path');
const { Server } = require('socket.io');
const cors = require('cors');
require('dotenv').config();

const app = express();
const server = createServer(app);

const io = new Server(server, {
    cors: { origin: '*', methods: ['GET', 'POST'] }
});

app.use(express.json({ limit: '2mb' }));
app.use(cors());

const publicidad = require('./publicidad')(io);

const TERMINAL_EVENTS = new Set([
    'clienteDisplayData',
    'clienteQrData',
    'clienteSaleComplete',
    'clienteDisplayClose'
]);

const terminalRoom = (agencia, caja) => {
    agencia = parseInt(agencia);
    caja = parseInt(caja);

    if (!agencia || !caja) return null;

    return `terminal:${agencia}:${caja}`;
};

// Página principal
app.get('/', (req, res) => {
    res.sendFile(join(__dirname, 'index.html'));
});

// Rutas antiguas
app.get('/send', (req, res) => {
    io.emit('reservas', 'Hola desde el servidor');
    res.send('Mensaje enviado');
});

app.get('/silSolicitud', (req, res) => {
    io.emit('silSolicitud', 'Hola desde el servidor');
    res.send('Mensaje enviado');
});

app.get('/votacion', (req, res) => {
    io.emit('votacion', {
        title: 'Nuevo dato registrado',
        message: 'Evento manual de votación',
        kind: 'manual'
    });

    res.json({ ok: true });
});

// Laravel / otros sistemas
app.post('/notify', (req, res) => {
    const { event, data = {} } = req.body;

    if (!event)
        return res.status(422).json({
            success: false,
            message: 'Evento obligatorio'
        });

    // Publicidad sincronizada (solo a las pantallas de esa agencia)
    if (event === 'ad_schedule') {
        try {
            publicidad.publish(data);
            return res.json({ success: true });
        } catch (e) {
            return res.status(422).json({ success: false, message: e.message });
        }
    }

    // Evento dirigido a una caja
    if (TERMINAL_EVENTS.has(event)) {
        const room = terminalRoom(data.agencia_id, data.caja);

        if (!room)
            return res.status(422).json({
                success: false,
                message: 'agencia_id y caja son obligatorios'
            });

        io.to(room).emit(event, data);

        console.log(`[TERMINAL] ${room} -> ${event}`);

        return res.json({
            success: true,
            room
        });
    }

    // Evento general para cualquier proyecto
    io.emit(event, data);

    console.log(`[GLOBAL] ${event}`);

    res.json({ success: true });
});

// Socket
io.on('connection', socket => {

    console.log('Conectado:', socket.id);

    // Registrar pantalla/caja
    socket.on('register_terminal', (data, callback) => {
        const room = terminalRoom(data?.agencia_id, data?.caja);

        if (!room) {
            return callback?.({ success: false });
        }

        socket.join(room);

        console.log(`${socket.id} registrado en ${room}`);

        callback?.({
            success: true,
            room
        });
    });

    // Sistemas anteriores
    socket.on('reservas-Ayacucho', msg =>
        io.emit('reservas-ayacucho', msg)
    );

    socket.on('reservas-Oquendo', msg =>
        io.emit('reservas-oquendo', msg)
    );

    socket.on('reservas-sucre-aventura', msg =>
        io.emit('reservas-sucre-aventura', msg)
    );

    socket.on('silSolicitud', msg =>
        io.emit('silSolicitud', msg)
    );

    socket.on('votacion', msg =>
        io.emit('votacion', msg)
    );

    socket.on('disconnect', () =>
        console.log('Desconectado:', socket.id)
    );
});

const PORT = process.env.PORT || 3014;

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Socket funcionando en puerto ${PORT}`);
});