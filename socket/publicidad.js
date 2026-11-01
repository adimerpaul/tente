// Publicidad sincronizada (Santidad: apk-publicidad y pantallaCobro).
// Ligero: sin timers ni polling. Laravel publica por /notify (event: ad_schedule),
// las pantallas reciben al registrarse y por push a su sala.
const fs = require('fs');
const { join } = require('path');

const DIR = join(__dirname, '.data', 'publicidad');
const states = new Map(); // agencia_id -> programación vigente

const agencyId = v => {
    const n = Number(v);
    return Number.isSafeInteger(n) && n > 0 ? n : null;
};

const valid = s =>
    s?.protocol === 2 && agencyId(s.agencia_id) && Number.isSafeInteger(s.revision) && s.revision > 0 &&
    Array.isArray(s.items) && s.items.length <= 2000 &&
    s.items.every(a => a && (a.type === 'image' || a.type === 'video') && typeof a.url === 'string');

const isReady = s => s.items.every(a => Number.isInteger(a.duration_ms) && a.duration_ms > 0);
const withClock = s => (s ? { ...s, server_time_ms: Date.now() } : null);

function save(s) {
    states.set(Number(s.agencia_id), s);
    try {
        const file = join(DIR, s.agencia_id + '.json');
        fs.writeFileSync(file + '.tmp', JSON.stringify(s));
        fs.renameSync(file + '.tmp', file);
    } catch (e) {
        console.error('[PUBLICIDAD] no se pudo guardar:', e.message);
    }
}

// Restaurar al reiniciar para que las pantallas no queden vacías.
try {
    fs.mkdirSync(DIR, { recursive: true });
    for (const f of fs.readdirSync(DIR)) {
        if (!/^\d+\.json$/.test(f)) continue;
        try {
            const s = JSON.parse(fs.readFileSync(join(DIR, f), 'utf8'));
            if (valid(s)) states.set(Number(s.agencia_id), s);
        } catch { /* archivo dañado: se ignora */ }
    }
} catch (e) {
    console.error('[PUBLICIDAD] sin persistencia:', e.message);
}

module.exports = function attachPublicidad(io) {
    const room = id => 'publicidad:' + id;

    function publish(data) {
        if (!valid(data)) throw new Error('Programación de publicidad no válida');
        const id = Number(data.agencia_id);
        const old = states.get(id);
        if (old && old.revision >= data.revision) return;

        const s = { ...data, items: data.items.map(a => ({ ...a })), epoch_ms: Date.now() + 5000 };
        delete s.server_time_ms;
        // Reusar duraciones ya medidas de videos sin cambios.
        for (const a of s.items) {
            const prev = old?.items.find(p => p.id === a.id && p.media_version === a.media_version);
            if (!a.duration_ms && prev?.duration_ms) a.duration_ms = prev.duration_ms;
        }
        s.ready = isReady(s);
        save(s);
        io.to(room(id)).emit('ad_schedule', withClock(s));
        console.log(`[PUBLICIDAD] agencia ${id} rev ${s.revision}${s.ready ? '' : ' (midiendo videos)'}`);
    }

    io.on('connection', socket => {
        const reply = (ack, revision) => {
            const s = states.get(socket.data.adAgency);
            ack({
                success: true,
                server_time_ms: Date.now(),
                revision: s?.revision ?? 0,
                needs_snapshot: false,
                pending_bootstrap: false,
                schedule: s && s.revision !== Number(revision) ? withClock(s) : null,
            });
        };

        socket.on('ad_register', (data, ack) => {
            const id = agencyId(data?.agencia_id);
            if (typeof ack !== 'function') return;
            if (!id) return ack({ success: false });
            if (socket.data.adAgency) socket.leave(room(socket.data.adAgency));
            socket.join(room(id));
            socket.data.adAgency = id;
            reply(ack, -1); // siempre enviar la programación al registrarse
        });

        socket.on('ad_clock', (data, ack) => {
            if (typeof ack !== 'function') return;
            if (!socket.data.adAgency) return ack({ success: false });
            reply(ack, data?.revision);
        });

        // Las pantallas miden la duración de videos nuevos una sola vez.
        socket.on('ad_durations', (data, ack) => {
            const done = ok => typeof ack === 'function' && ack({ success: ok });
            const s = states.get(socket.data.adAgency);
            if (!s || s.ready || data?.revision !== s.revision || !Array.isArray(data.items) || data.items.length > 100)
                return done(false);
            for (const it of data.items) {
                const a = s.items.find(x => x.id === it.id && x.media_version === it.media_version && x.type === 'video');
                if (a && !a.duration_ms && Number.isInteger(it.duration_ms) && it.duration_ms >= 100 && it.duration_ms <= 86400000)
                    a.duration_ms = it.duration_ms;
            }
            if (isReady(s)) {
                s.ready = true;
                s.epoch_ms = Date.now() + 5000;
                save(s);
                io.to(room(s.agencia_id)).emit('ad_schedule', withClock(s));
            }
            done(true);
        });

        // ad_received / ad_status / terminal_status: no se necesitan aquí; socket.io los descarta.
    });

    return { publish, close() {} };
};
