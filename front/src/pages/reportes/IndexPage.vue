<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm q-col-gutter-sm">
      <div class="col-12 col-md"><div class="text-subtitle1 text-weight-bold">Reportes de ventas</div><div class="text-caption text-grey-7">Del {{fmt(data.desde)}} al {{fmt(data.hasta)}} · sólo ventas completadas</div></div>
      <div class="col-auto row q-gutter-xs items-center">
        <q-btn-toggle v-model="quick" dense no-caps unelevated toggle-color="primary" color="grey-2" text-color="grey-9" :options="quickOptions" @update:model-value="applyQuick"/>
      </div>
    </div>
    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row q-col-gutter-sm q-pa-sm items-center">
        <q-input v-model="from" dense outlined type="date" label="Desde" class="col-6 col-md-2" @update:model-value="quick=null;load()"/>
        <q-input v-model="to" dense outlined type="date" label="Hasta" class="col-6 col-md-2" @update:model-value="quick=null;load()"/>
        <q-select v-model="user" :options="data.usuarios" option-label="name" dense outlined clearable label="Vendedor" class="col-12 col-md-3" @update:model-value="load"/>
        <q-space/>
        <div class="col-auto"><q-btn dense unelevated color="positive" icon="table_view" label="Excel" no-caps class="q-mr-xs" :loading="downloading==='excel'" @click="download('excel')"/><q-btn dense unelevated color="secondary" icon="picture_as_pdf" label="PDF" no-caps :loading="downloading==='pdf'" @click="download('pdf')"/></div>
      </q-card-section>
    </q-card>

    <div class="kpi-grid q-mb-sm">
      <q-card v-for="k in kpis" :key="k.label" flat bordered class="kpi"><q-card-section class="q-pa-sm row items-center no-wrap"><q-avatar :style="{background:k.color}" text-color="white" :icon="k.icon" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">{{k.label}}</div><div class="text-h6 text-weight-bold">{{k.value}}</div></div></q-card-section></q-card>
    </div>

    <q-inner-loading :showing="loading"/>
    <div class="row q-col-gutter-sm">
      <div class="col-12 col-lg-7">
        <q-card flat bordered><q-card-section class="q-py-xs q-px-sm card-title">Productos vendidos</q-card-section><q-separator/>
          <q-table dense flat :rows="data.por_producto" :columns="productColumns" row-key="nombre" :rows-per-page-options="[0]" hide-pagination no-data-label="Sin ventas en el periodo">
            <template #body-cell-share="p"><q-td :props="p"><q-linear-progress :value="share(p.row.total)" color="primary" track-color="orange-1" rounded size="8px" style="width:90px;display:inline-block"/> <span class="text-caption">{{(share(p.row.total)*100).toFixed(0)}}%</span></q-td></template>
          </q-table>
        </q-card>
      </div>
      <div class="col-12 col-lg-5">
        <q-card flat bordered class="q-mb-sm"><q-card-section class="q-py-xs q-px-sm card-title">Por categoría</q-card-section><q-separator/><q-table dense flat :rows="data.por_categoria" :columns="simpleColumns('Categoría')" row-key="nombre" :rows-per-page-options="[0]" hide-pagination/></q-card>
        <q-card flat bordered class="q-mb-sm"><q-card-section class="q-py-xs q-px-sm card-title">Por vendedor</q-card-section><q-separator/><q-table dense flat :rows="data.por_usuario" :columns="userColumns" row-key="nombre" :rows-per-page-options="[0]" hide-pagination/></q-card>
        <div class="row q-col-gutter-sm">
          <div class="col-6"><q-card flat bordered><q-card-section class="q-py-xs q-px-sm card-title">Forma de pago</q-card-section><q-separator/><q-table dense flat :rows="data.por_pago" :columns="simpleColumns('Pago')" row-key="nombre" :rows-per-page-options="[0]" hide-pagination/></q-card></div>
          <div class="col-6"><q-card flat bordered><q-card-section class="q-py-xs q-px-sm card-title">Mesa / llevar</q-card-section><q-separator/><q-table dense flat :rows="data.por_consumo" :columns="simpleColumns('Consumo')" row-key="nombre" :rows-per-page-options="[0]" hide-pagination/></q-card></div>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat bordered><q-card-section class="q-py-xs q-px-sm card-title">Ventas por hora</q-card-section><q-separator/>
          <div class="hours q-pa-sm"><div v-for="h in data.por_hora" :key="h.hora" class="hours__row"><span class="hours__label">{{String(h.hora).padStart(2,'0')}}:00</span><div class="hours__bar"><div :style="{width:`${hourShare(h.total)*100}%`}"/></div><span class="hours__value">{{h.cantidad}} · Bs {{money(h.total)}}</span></div><div v-if="!data.por_hora.length" class="text-grey-6 text-center">Sin datos</div></div>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat bordered class="q-mb-sm"><q-card-section class="q-py-xs q-px-sm card-title">Por día</q-card-section><q-separator/><q-table dense flat :rows="data.por_dia" :columns="dayColumns" row-key="dia" :rows-per-page-options="[0]" hide-pagination/></q-card>
        <q-card flat bordered><q-card-section class="q-py-xs q-px-sm card-title">Mejores clientes</q-card-section><q-separator/><q-table dense flat :rows="data.por_cliente" :columns="clientColumns" row-key="nombre" :rows-per-page-options="[0]" hide-pagination no-data-label="Sin clientes registrados en el periodo"/></q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, reactive, ref } from 'vue'
const { proxy }=getCurrentInstance()
const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`,today=()=>iso(new Date())
const from=ref(today()),to=ref(today()),user=ref(null),loading=ref(false),downloading=ref(null),quick=ref('hoy')
const quickOptions=[{label:'Hoy',value:'hoy'},{label:'Ayer',value:'ayer'},{label:'7 días',value:'semana'},{label:'Este mes',value:'mes'}]
const data=reactive({desde:today(),hasta:today(),resumen:{},por_producto:[],por_categoria:[],por_usuario:[],por_dia:[],por_hora:[],por_pago:[],por_consumo:[],por_cliente:[],usuarios:[]})
const money=v=>Number(v||0).toLocaleString('es-BO',{minimumFractionDigits:2,maximumFractionDigits:2}),fmt=v=>v?v.split('-').reverse().join('/'):''
const r=computed(()=>data.resumen||{})
const kpis=computed(()=>[
  {label:'Total vendido',value:`Bs ${money(r.value.total)}`,icon:'payments',color:'#9f2321'},
  {label:'Pedidos',value:r.value.cantidad||0,icon:'receipt_long',color:'#e9682f'},
  {label:'Platos vendidos',value:r.value.platos||0,icon:'lunch_dining',color:'#f6a204'},
  {label:'Ticket promedio',value:`Bs ${money(r.value.ticket_promedio)}`,icon:'local_offer',color:'#6d4c41'},
  {label:'Efectivo',value:`Bs ${money(r.value.efectivo)}`,icon:'savings',color:'#2e7d32'},
  {label:'QR',value:`Bs ${money(r.value.qr)}`,icon:'qr_code_2',color:'#1565c0'},
  {label:'Descuentos',value:`Bs ${money(r.value.descuento)}`,icon:'sell',color:'#8e24aa'},
  {label:'Anuladas',value:`${r.value.anuladas||0} · Bs ${money(r.value.total_anulado)}`,icon:'cancel',color:'#757575'},
])
const share=v=>r.value.total?Number(v)/Number(r.value.total):0
const maxHour=computed(()=>Math.max(1,...data.por_hora.map(h=>Number(h.total)))),hourShare=v=>Number(v)/maxHour.value
const productColumns=[{name:'nombre',label:'Producto',field:'nombre',align:'left'},{name:'categoria',label:'Categoría',field:'categoria',align:'left'},{name:'cantidad',label:'Cant.',field:'cantidad',align:'center'},{name:'total',label:'Total',field:'total',format:v=>`Bs ${money(v)}`,align:'right'},{name:'share',label:'% del total',align:'left'}]
const simpleColumns=label=>[{name:'nombre',label,field:'nombre',align:'left',format:v=>v==='LLEVAR'?'PARA LLEVAR':v==='MESA'?'EN MESA':v},{name:'cantidad',label:'Cant.',field:'cantidad',align:'center'},{name:'total',label:'Total',field:'total',format:v=>`Bs ${money(v)}`,align:'right'}]
const userColumns=[{name:'nombre',label:'Vendedor',field:'nombre',align:'left'},{name:'cantidad',label:'Pedidos',field:'cantidad',align:'center'},{name:'efectivo',label:'Efectivo',field:'efectivo',format:v=>`Bs ${money(v)}`,align:'right'},{name:'qr',label:'QR',field:'qr',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',format:v=>`Bs ${money(v)}`,align:'right'}]
const dayColumns=[{name:'dia',label:'Día',field:'dia',format:v=>fmt(String(v).slice(0,10)),align:'left'},{name:'cantidad',label:'Pedidos',field:'cantidad',align:'center'},{name:'efectivo',label:'Efectivo',field:'efectivo',format:v=>`Bs ${money(v)}`,align:'right'},{name:'qr',label:'QR',field:'qr',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',format:v=>`Bs ${money(v)}`,align:'right'}]
const clientColumns=[{name:'nombre',label:'Cliente',field:'nombre',align:'left'},{name:'documento',label:'CI/NIT',field:'documento',align:'left'},{name:'cantidad',label:'Pedidos',field:'cantidad',align:'center'},{name:'total',label:'Total',field:'total',format:v=>`Bs ${money(v)}`,align:'right'}]
const params=()=>({desde:from.value,hasta:to.value,user_id:user.value?.id})
function applyQuick(v){const d=new Date();if(v==='hoy'){from.value=to.value=iso(d)}else if(v==='ayer'){d.setDate(d.getDate()-1);from.value=to.value=iso(d)}else if(v==='semana'){to.value=iso(d);d.setDate(d.getDate()-6);from.value=iso(d)}else if(v==='mes'){to.value=iso(d);from.value=iso(new Date(d.getFullYear(),d.getMonth(),1))}load()}
function load(){loading.value=true;proxy.$axios.get('/reportes',{params:params()}).then(res=>Object.assign(data,res.data)).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el reporte')).finally(()=>loading.value=false)}
async function download(type){downloading.value=type;try{const res=await proxy.$axios.get(`/reportes-exportar/${type}`,{params:params(),responseType:'blob'});const url=URL.createObjectURL(res.data),a=document.createElement('a');a.href=url;a.download=`reporte_ventas_${from.value}_${to.value}.${type==='excel'?'xlsx':'pdf'}`;a.click();URL.revokeObjectURL(url)}catch{proxy.$alert.error('No se pudo exportar el reporte')}finally{downloading.value=null}}
load()
</script>

<style scoped>
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.kpi{border-radius:10px}.card-title{font-weight:700;font-size:13px;color:#9f2321}
.hours__row{display:flex;align-items:center;gap:6px;font-size:11px;margin-bottom:3px}.hours__label{width:40px;color:#666}.hours__bar{flex:1;height:12px;background:#fdf0e7;border-radius:6px;overflow:hidden}.hours__bar div{height:100%;background:linear-gradient(90deg,#f6a204,#e9682f);border-radius:6px}.hours__value{width:120px;text-align:right}
@media(max-width:900px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
