<template>
  <q-page class="q-pa-sm kitchen">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Cocina</div><div class="text-caption text-grey-7">Pedidos de hoy · <span :class="live?'text-positive':'text-negative'"><q-icon :name="live?'wifi':'wifi_off'"/> {{live?'en tiempo real':'sin socket, se actualiza cada '+REFRESH/1000+' s'}}</span></div></div>
      <q-space/>
      <q-toggle v-model="sound" dense size="sm" icon="volume_up" label="Voz" class="q-mr-sm" @update:model-value="v=>saveVoiceSettings({enabled:v})"/>
      <q-btn dense flat round icon="refresh" color="primary" :loading="loading" @click="load"/>
    </div>

    <div class="kitchen-board">
      <div v-for="col in columns" :key="col.estado" class="kitchen-col" :class="`kitchen-col--${col.estado.toLowerCase()}`">
        <div class="kitchen-col__head"><q-icon :name="col.icon" size="20px"/> {{col.label}} <q-badge rounded color="white" text-color="dark" :label="byState(col.estado).length" class="q-ml-xs"/></div>
        <div class="kitchen-col__body">
          <q-card v-for="p in byState(col.estado)" :key="p.id" flat bordered class="order" :class="{'order--late':minutes(p)>=15&&col.estado!=='LISTO'}">
            <q-card-section class="q-pa-sm">
              <div class="row items-center no-wrap">
                <div class="order__ticket">#{{p.ticket}}</div>
                <div class="q-ml-sm col ellipsis"><div class="text-weight-bold ellipsis">{{p.cliente_nombre}}</div><div class="text-caption text-grey-7">{{time(p.fecha)}} · hace {{minutes(p)}} min</div></div>
                <q-chip dense square :color="p.tipo_consumo==='LLEVAR'?'accent':'blue-grey-2'" :text-color="p.tipo_consumo==='LLEVAR'?'white':'dark'" :icon="p.tipo_consumo==='LLEVAR'?'takeout_dining':'restaurant'" :label="p.tipo_consumo==='LLEVAR'?'LLEVAR':'MESA'"/>
              </div>
              <q-separator class="q-my-xs"/>
              <div v-for="d in p.detalles" :key="d.id" class="order__line"><b>{{d.cantidad}}×</b> {{d.nombre}}<div v-if="d.observacion" class="order__note">» {{d.observacion}}</div></div>
              <div v-if="p.observacion" class="order__note q-mt-xs"><q-icon name="sticky_note_2"/> {{p.observacion}}</div>
            </q-card-section>
            <q-card-actions v-if="canUpdate" class="q-pa-xs">
              <q-btn v-if="col.prev" dense flat no-caps size="sm" color="grey-7" icon="undo" @click="move(p,col.prev)"/>
              <q-space/>
              <q-btn dense unelevated no-caps :color="col.nextColor" :icon="col.nextIcon" :label="col.nextLabel" :loading="moving===p.id" @click="move(p,col.next)"/>
            </q-card-actions>
          </q-card>
          <div v-if="!byState(col.estado).length" class="text-center text-grey-6 q-pa-md">Sin pedidos</div>
        </div>
      </div>
    </div>

    <q-card v-if="delivered.length" flat bordered class="q-mt-sm">
      <q-card-section class="q-py-xs text-caption text-weight-bold text-grey-8">Últimos entregados</q-card-section>
      <q-separator/>
      <div class="row q-pa-xs q-gutter-xs">
        <q-chip v-for="p in delivered" :key="p.id" dense outline color="positive" icon="check">#{{p.ticket}} {{p.cliente_nombre}}
          <q-btn v-if="canUpdate" dense flat round size="xs" icon="undo" color="grey-8" @click="move(p,'LISTO')"><q-tooltip>Volver a listos</q-tooltip></q-btn>
        </q-chip>
      </div>
    </q-card>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, onUnmounted, ref } from 'vue'
import { connectSocket, onCocina, saveVoiceSettings, socketConnected, voiceSettings } from '../../addons/cocinaSocket'
const { proxy } = getCurrentInstance()
const REFRESH=8000
const orders=ref([]),delivered=ref([]),loading=ref(false),moving=ref(null),now=ref(Date.now()),sound=ref(voiceSettings().enabled),live=ref(false)
const canUpdate=computed(()=>proxy.$store.hasPermission('Actualizar Pedidos'))
const columns=[
  {estado:'PENDIENTE',label:'Pendientes',icon:'notifications_active',prev:null,next:'PREPARANDO',nextLabel:'Preparar',nextIcon:'local_fire_department',nextColor:'primary'},
  {estado:'PREPARANDO',label:'En preparación',icon:'outdoor_grill',prev:'PENDIENTE',next:'LISTO',nextLabel:'Listo',nextIcon:'done',nextColor:'accent'},
  {estado:'LISTO',label:'Listos para entregar',icon:'room_service',prev:'PREPARANDO',next:'ENTREGADO',nextLabel:'Entregado',nextIcon:'done_all',nextColor:'positive'},
]
const byState=s=>orders.value.filter(o=>o.estado_cocina===s)
const time=v=>new Date(v).toLocaleTimeString('es-BO',{hour:'2-digit',minute:'2-digit'}),minutes=p=>Math.max(0,Math.floor((now.value-new Date(p.fecha).getTime())/60000))
let timer=null,clock=null,known=null,offCocina=null,socket=null,reloadT=null
// Con socket la lista se recarga al llegar cada aviso (la voz y el mensaje los pone MainLayout); el sondeo queda de respaldo.
const setLive=()=>{live.value=socketConnected()},onAviso=()=>{clearTimeout(reloadT);reloadT=setTimeout(load,250)}
// Pitido corto con WebAudio cuando entra un pedido nuevo; no necesita archivos de sonido.
function beep(){if(!sound.value||live.value)return;try{const ctx=new (window.AudioContext||window.webkitAudioContext)(),o=ctx.createOscillator(),g=ctx.createGain();o.frequency.value=880;o.connect(g);g.connect(ctx.destination);g.gain.setValueAtTime(.2,ctx.currentTime);g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+.6);o.start();o.stop(ctx.currentTime+.6)}catch{/* sin audio */}}
function load(){loading.value=true;proxy.$axios.get('/cocina').then(({data})=>{const ids=new Set(data.pedidos.map(p=>p.id));if(known&&[...ids].some(id=>!known.has(id)))beep();known=ids;orders.value=data.pedidos;delivered.value=data.entregados}).catch(e=>{if(e.response)proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los pedidos')}).finally(()=>loading.value=false)}
function move(p,estado){moving.value=p.id;proxy.$axios.put(`/ventas/${p.id}/cocina`,{estado_cocina:estado}).then(load).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo actualizar')).finally(()=>moving.value=null)}
onMounted(()=>{load();timer=setInterval(()=>{if(!live.value)load()},REFRESH);clock=setInterval(()=>{now.value=Date.now();if(live.value)load()},30000);socket=connectSocket();socket?.on('connect',setLive);socket?.on('disconnect',setLive);socket?.on('connect',load);setLive();offCocina=onCocina(onAviso)})
onUnmounted(()=>{clearInterval(timer);clearInterval(clock);clearTimeout(reloadT);offCocina?.();socket?.off('connect',setLive);socket?.off('disconnect',setLive);socket?.off('connect',load)})
</script>

<style scoped>
.kitchen-board{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.kitchen-col{border-radius:12px;background:#fff;border:1px solid #f1d9c8;display:flex;flex-direction:column;min-height:60vh}
.kitchen-col__head{padding:8px 10px;color:#fff;font-weight:800;border-radius:12px 12px 0 0;display:flex;align-items:center;gap:6px}.kitchen-col--pendiente .kitchen-col__head{background:#9f2321}.kitchen-col--preparando .kitchen-col__head{background:#e9682f}.kitchen-col--listo .kitchen-col__head{background:#2e7d32}
.kitchen-col__body{padding:6px;display:flex;flex-direction:column;gap:6px;overflow:auto}.order{border-radius:10px;border-left:5px solid #f6a204}.order--late{border-left-color:#d32f2f;background:#fff5f5}
.order__ticket{font-size:24px;font-weight:900;color:#9f2321;line-height:1}.order__line{font-size:14px;line-height:1.3}.order__note{font-size:12px;color:#c62828;font-style:italic;padding-left:14px}
@media(max-width:900px){.kitchen-board{grid-template-columns:1fr}.kitchen-col{min-height:auto}}
</style>
