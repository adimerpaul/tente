<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-subtitle1 text-weight-bold">Ventas</div><div class="text-caption text-grey-7">Ventas de hoy · cambia las fechas para ver el historial</div></div><q-space/>
      <q-btn v-if="can('Ver Reportes')" dense flat color="primary" icon="insights" label="Reportes" no-caps class="q-mr-xs" to="/reportes"/>
      <q-btn v-if="can('Crear Ventas')" dense unelevated color="primary" icon="point_of_sale" label="Nueva venta" no-caps to="/ventas/nueva"/>
    </div>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div v-for="card in cards" :key="card.label" class="col-6 col-md-3"><q-card flat bordered class="summary-card"><q-card-section class="row items-center q-pa-sm"><q-avatar :style="{background:card.color}" text-color="white" :icon="card.icon" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">{{card.label}}</div><div class="text-h6 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div></div></q-card-section></q-card></div>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm">
        <q-input v-model="search" dense outlined debounce="300" clearable placeholder="Buscar número, cliente, vendedor o estado" class="col-12 col-md-4" @update:model-value="load"><template #prepend><q-icon name="search"/></template></q-input>
        <q-select v-model="user" :options="summary.usuarios" option-label="name" dense outlined clearable label="Vendedor" class="col-12 col-md-2" @update:model-value="load"/>
        <q-select v-model="payment" :options="['EFECTIVO','QR','COMBINADO']" dense outlined clearable label="Pago" class="col-6 col-md-2" @update:model-value="load"/>
        <q-input v-model="from" dense outlined clearable type="date" label="Desde" class="col-6 col-md-2" @update:model-value="load"/><q-input v-model="to" dense outlined clearable type="date" label="Hasta" class="col-6 col-md-2" @update:model-value="load"/>
        <div class="col-auto self-center"><q-btn dense flat no-caps color="primary" icon="today" label="Hoy" @click="setToday"/><q-btn dense flat no-caps color="grey-8" icon="event_repeat" label="Todo" @click="clearDates"/></div>
      </q-card-section>
      <q-table dense flat :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[50,100,200]" @request="onRequest">
        <template #body-cell-ticket="p"><q-td :props="p"><b class="text-secondary">#{{p.value}}</b><div class="text-caption text-grey-7">{{p.row.numero}}</div></q-td></template>
        <template #body-cell-cliente="p"><q-td :props="p"><div class="text-weight-medium">{{p.row.cliente_nombre}}</div><q-badge outline :color="p.row.tipo_consumo==='LLEVAR'?'accent':'blue-grey'" :label="p.row.tipo_consumo==='LLEVAR'?'PARA LLEVAR':'EN MESA'"/></q-td></template>
        <template #body-cell-total="p"><q-td :props="p"><b>Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-tipo_pago="p"><q-td :props="p"><q-chip dense square :color="paymentColor(p.value)" text-color="white" :icon="p.value==='QR'?'qr_code_2':p.value==='EFECTIVO'?'payments':'account_balance_wallet'">{{p.value}}</q-chip></q-td></template>
        <template #body-cell-detalles="p"><q-td :props="p"><div class="venta-productos"><div v-for="d in (p.row.detalles||[]).slice(0,maxItems)" :key="d.id" class="ellipsis"><b class="text-primary">{{d.cantidad}}</b> {{d.nombre}}</div><div v-if="(p.row.detalles||[]).length>maxItems" class="text-grey-7">+{{p.row.detalles.length-maxItems}} más</div></div></q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value==='COMPLETADA'?'positive':'negative'" :label="p.value"/><div v-if="p.value==='COMPLETADA'"><q-badge outline :color="kitchenColor(p.row.estado_cocina)" :label="p.row.estado_cocina" class="q-mt-xs"/></div></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none"><q-list dense style="min-width:170px">
          <q-item clickable v-close-popup @click="showDetail(p.row)"><q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section><q-item-section>Ver detalle</q-item-section></q-item>
          <q-item clickable v-close-popup @click="printRow(p.row,printSale)"><q-item-section avatar><q-icon name="print" color="blue-grey"/></q-item-section><q-item-section>Ticket del cliente</q-item-section></q-item>
          <q-item clickable v-close-popup @click="printRow(p.row,printKitchen)"><q-item-section avatar><q-icon name="soup_kitchen" color="orange"/></q-item-section><q-item-section>Comanda de cocina</q-item-section></q-item>
          <q-separator/>
          <q-item v-if="can('Anular Ventas')&&p.row.estado==='COMPLETADA'" clickable v-close-popup class="text-negative" @click="cancel(p.row)"><q-item-section avatar><q-icon name="cancel"/></q-item-section><q-item-section>Anular</q-item-section></q-item>
        </q-list></q-btn-dropdown></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog"><q-card style="width:720px;max-width:96vw">
      <q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">Pedido #{{selected.ticket}} · {{selected.numero}}</div><div class="text-caption">{{formatDate(selected.fecha)}} · Atendió {{selected.usuario_nombre}}</div><div class="text-caption">Cliente: <b>{{selected.cliente_nombre}}</b><span v-if="selected.cliente_documento"> ({{selected.cliente_documento}})</span> · {{selected.tipo_consumo==='LLEVAR'?'Para llevar':'En mesa'}}</div></div><q-space/><q-btn flat round dense icon="print" color="primary" @click="printSale(selected)"/><q-badge :color="selected.estado==='COMPLETADA'?'positive':'negative'" :label="selected.estado"/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/>
      <q-table dense flat :rows="selected.detalles||[]" :columns="detailColumns" row-key="id" hide-pagination :rows-per-page-options="[0]"><template #body-cell-nombre="p"><q-td :props="p">{{p.value}}<div v-if="p.row.observacion" class="text-caption text-negative">» {{p.row.observacion}}</div></q-td></template><template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template></q-table><q-separator/>
      <q-card-section class="q-pa-sm"><div class="row"><span>Subtotal</span><q-space/>Bs {{money(selected.subtotal)}}</div><div class="row text-negative"><span>Descuento</span><q-space/>- Bs {{money(selected.descuento)}}</div><div class="row"><span>Efectivo</span><q-space/>Bs {{money(selected.monto_efectivo)}}</div><div class="row"><span>QR</span><q-space/>Bs {{money(selected.monto_qr)}}</div><div class="row text-h6 text-secondary"><b>Total</b><q-space/><b>Bs {{money(selected.total)}}</b></div><div v-if="selected.observacion" class="text-caption text-grey-8 q-mt-xs">Obs.: {{selected.observacion}}</div></q-card-section>
    </q-card></q-dialog>
  </q-page>
</template>

<script setup>
import {computed,getCurrentInstance,reactive,ref} from 'vue'
import {printKitchen,printSale} from '../../addons/ventaPrint'
// La pantalla abre con las ventas de hoy; limpiando las fechas se ve todo el historial.
const today=()=>{const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`}
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),search=ref(''),from=ref(today()),to=ref(today()),user=ref(null),payment=ref(null),dialog=ref(false),selected=reactive({}),summary=reactive({efectivo:0,qr:0,total:0,descuento:0,cantidad:0,usuarios:[]})
const pagination=ref({page:1,rowsPerPage:50,rowsNumber:0}),money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p),maxItems=4
const cards=computed(()=>[{label:'Total vendido',value:summary.total,money:true,icon:'trending_up',color:'#9f2321'},{label:'Efectivo',value:summary.efectivo,money:true,icon:'payments',color:'#2e7d32'},{label:'QR',value:summary.qr,money:true,icon:'qr_code_2',color:'#1565c0'},{label:'Pedidos',value:summary.cantidad,money:false,icon:'receipt_long',color:'#e9682f'}])
const columns=[{name:'actions',label:'',align:'left'},{name:'ticket',label:'Pedido',field:'ticket',align:'left'},{name:'fecha',label:'Fecha',field:r=>formatDate(r.fecha),align:'left'},{name:'cliente',label:'Cliente',field:'cliente_nombre',align:'left'},{name:'usuario',label:'Vendedor',field:'usuario_nombre',align:'left'},{name:'detalles',label:'Productos',field:'detalles',align:'left'},{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'descuento',label:'Desc.',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
const detailColumns=[{name:'cantidad',label:'Cant.',field:'cantidad',align:'center'},{name:'nombre',label:'Producto',field:'nombre',align:'left'},{name:'precio',label:'Precio',field:'precio_venta',format:v=>`Bs ${money(v)}`,align:'right'},{name:'descuento',label:'Desc.',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'}]
const params=()=>({q:search.value,desde:from.value,hasta:to.value,user_id:user.value?.id,tipo_pago:payment.value}),paymentColor=v=>v==='EFECTIVO'?'green':v==='QR'?'blue':'purple'
const kitchenColor=v=>({PENDIENTE:'negative',PREPARANDO:'orange',LISTO:'accent',ENTREGADO:'positive'})[v]||'grey'
function setToday(){from.value=to.value=today();load()}
function clearDates(){from.value=to.value='';load()}
function formatDate(v){return v?new Date(v).toLocaleString('es-BO'):''}function load(){onRequest({pagination:{...pagination.value,page:1}})}function loadSummary(){proxy.$axios.get('/ventas-resumen',{params:params()}).then(r=>Object.assign(summary,r.data))}
function onRequest({pagination:p}){loading.value=true;proxy.$axios.get('/ventas',{params:{...params(),page:p.page,per_page:p.rowsPerPage}}).then(r=>{rows.value=r.data.data;pagination.value={...p,rowsNumber:r.data.total};loadSummary()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar las ventas')).finally(()=>loading.value=false)}
function showDetail(row){proxy.$axios.get(`/ventas/${row.id}`).then(r=>{Object.assign(selected,r.data);dialog.value=true})}function printRow(row,printer){proxy.$axios.get(`/ventas/${row.id}`).then(r=>printer(r.data))}
function cancel(row){proxy.$alert.dialog(`¿Anular la venta ${row.numero}?`,'El pedido también sale de la pantalla de cocina.').onOk(()=>proxy.$axios.put(`/ventas/${row.id}/anular`).then(()=>{proxy.$alert.success('Venta anulada');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular')))}
load()
</script>

<style scoped>.summary-card{border-radius:10px}.venta-productos{font-size:11px;line-height:13px;max-width:260px}</style>
