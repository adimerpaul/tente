import { io } from 'socket.io-client'

// Servidor socket compartido por todos los proyectos. El backend publica por su
// canal genérico POST /notify y el servidor reemite `tente-cocina` a todos.
export const EVENTO_COCINA = 'tente-cocina'

let socket = null
const listeners = new Set()

export function connectSocket () {
  if (socket || !import.meta.env.VITE_API_SOCKET) return socket
  socket = io(import.meta.env.VITE_API_SOCKET, { transports: ['websocket', 'polling'] })
  socket.on(EVENTO_COCINA, data => listeners.forEach(fn => { try { fn(data) } catch (e) { console.error(e) } }))
  return socket
}

export const socketConnected = () => !!socket?.connected

/** Suscribe a los avisos de cocina; devuelve la función para desuscribirse. */
export function onCocina (fn) {
  connectSocket()
  listeners.add(fn)
  return () => listeners.delete(fn)
}

// ── Voz (Web Speech API; en Chrome/Android trae voces "Google español") ──

const KEY = 'vozTente'
const DEFAULTS = { enabled: true, voiceURI: '', rate: 1, pitch: 1 }

export function voiceSettings () {
  try { return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(KEY) || '{}') } } catch { return { ...DEFAULTS } }
}

export function saveVoiceSettings (s) {
  localStorage.setItem(KEY, JSON.stringify({ ...voiceSettings(), ...s }))
}

const synth = typeof window !== 'undefined' ? window.speechSynthesis : null
export const voiceSupported = () => !!synth

/** Voces en español primero, y entre ellas las de Google. Las voces cargan asíncronas en Chrome. */
export function loadVoices () {
  return new Promise(resolve => {
    if (!synth) return resolve([])
    const sort = list => [...list].sort((a, b) => score(b) - score(a) || a.name.localeCompare(b.name))
    const score = v => (v.lang?.toLowerCase().startsWith('es') ? 2 : 0) + (/google/i.test(v.name) ? 1 : 0)
    const now = synth.getVoices()
    if (now.length) return resolve(sort(now))
    const done = () => { synth.removeEventListener('voiceschanged', done); resolve(sort(synth.getVoices())) }
    synth.addEventListener('voiceschanged', done)
    setTimeout(done, 1500)
  })
}

export async function speak (text, override = {}) {
  const s = { ...voiceSettings(), ...override }
  if (!synth || !text || (!s.enabled && !override.force)) return
  const voices = await loadVoices()
  const voice = voices.find(v => v.voiceURI === s.voiceURI) || voices.find(v => /google/i.test(v.name) && v.lang?.toLowerCase().startsWith('es')) || voices.find(v => v.lang?.toLowerCase().startsWith('es'))
  const u = new SpeechSynthesisUtterance(text)
  if (voice) { u.voice = voice; u.lang = voice.lang } else u.lang = 'es-ES'
  u.rate = Number(s.rate) || 1
  u.pitch = Number(s.pitch) || 1
  synth.speak(u) // se encola: varios avisos seguidos se leen uno tras otro
}
