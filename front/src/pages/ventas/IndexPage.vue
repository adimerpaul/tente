<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-subtitle1 text-weight-bold">Ventas</div><div class="text-caption text-grey-7">Ventas de hoy · cambia las fechas para ver el historial</div></div><q-space/>
      <q-btn v-if="can('Ver Reportes')" dense flat color="primary" icon="insights" label="Reportes" no-caps class="q-mr-xs" to="/reportes"/>
      <q-btn-dropdown v-if="can('Registrar Movimientos')" dense unelevated color="secondary" icon="account_balance_wallet" label="Ingreso / egreso" no-caps class="q-mr-xs">
        <q-list dense style="min-width:200px">
          <q-item clickable v-close-popup @click="openMov('EGRESO')"><q-item-section avatar><q-icon name="north_east" color="negative"/></q-item-section><q-item-section><q-item-label>Egreso</q-item-label><q-item-label caption>Compra de periódicos, gas, aceite…</q-item-label></q-item-section></q-item>
          <q-item clickable v-close-popup @click="openMov('INGRESO')"><q-item-section avatar><q-icon name="south_west" color="positive"/></q-item-section><q-item-section><q-item-label>Ingreso</q-item-label><q-item-label caption>Venta de aceite usado, otros…</q-item-label></q-item-section></q-item>
        </q-list>
      </q-btn-dropdown>
      <q-btn v-if="can('Crear Ventas')" dense unelevated color="primary" icon="point_of_sale" label="Nueva venta" no-caps to="/ventas/nueva"/>
    </div>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div v-for="card in cards" :key="card.label" class="col-6 col-sm-4 col-md-2"><q-card flat bordered class="summary-card" :class="card.strong&&'summary-card--strong'"><q-card-section class="row items-center no-wrap q-pa-sm"><q-avatar :style="{background:card.color}" text-color="white" :icon="card.icon" size="34px"/><div class="q-ml-sm ellipsis"><div class="text-caption text-grey-7 ellipsis">{{card.label}}</div><div class="text-subtitle1 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div></div></q-card-section></q-card></div>
    </div>

    <q-tabs v-if="can('Ver Movimientos')" v-model="tab" dense no-caps align="left" active-color="secondary" indicator-color="primary" class="text-grey-8">
      <q-tab name="ventas" icon="receipt_long" label="Ventas"/><q-tab name="gastos" icon="account_balance_wallet" :label="`Ingresos y gastos (${summary.movimientos_cantidad||0})`"/>
    </q-tabs>

    <q-card v-if="tab==='gastos'" flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm items-center">
        <q-input v-model="movSearch" dense outlined debounce="300" clearable placeholder="Buscar concepto, categoría o usuario" class="col-12 col-md-4" @update:model-value="loadMovs"><template #prepend><q-icon name="search"/></template></q-input>
        <q-btn-toggle v-model="movTipo" dense no-caps unelevated toggle-color="secondary" color="grey-2" text-color="grey-9" :options="[{label:'Todos',value:null},{label:'Ingresos',value:'INGRESO',icon:'south_west'},{label:'Gastos',value:'EGRESO',icon:'north_east'}]" @update:model-value="loadMovs"/>
        <q-input v-model="from" dense outlined clearable type="date" label="Desde" class="col-6 col-md-2" @update:model-value="load"/><q-input v-model="to" dense outlined clearable type="date" label="Hasta" class="col-6 col-md-2" @update:model-value="load"/>
        <div class="col-auto"><q-btn dense flat no-caps color="primary" icon="today" label="Hoy" @click="setToday"/><q-btn dense flat no-caps color="grey-8" icon="event_repeat" label="Todo" @click="clearDates"/></div>
      </q-card-section>
      <q-table dense flat :rows="movs" :columns="movColumns" row-key="id" :loading="movLoading" :rows-per-page-options="[0]" hide-pagination no-data-label="Sin ingresos ni gastos en el periodo">
        <template #body-cell-tipo="p"><q-td :props="p"><q-chip dense square :color="p.value==='INGRESO'?'positive':'negative'" text-color="white" :icon="p.value==='INGRESO'?'south_west':'north_east'" :label="p.value==='INGRESO'?'INGRESO':'GASTO'"/></q-td></template>
        <template #body-cell-concepto="p"><q-td :props="p"><div class="text-weight-medium">{{p.value}}</div><div v-if="p.row.observacion" class="text-caption text-grey-7">{{p.row.observacion}}</div></q-td></template>
        <template #body-cell-monto="p"><q-td :props="p"><b :class="p.row.estado==='ANULADO'?'text-grey-6 text-strike':p.row.tipo==='INGRESO'?'text-positive':'text-negative'">{{p.row.tipo==='INGRESO'?'+':'−'}} Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value==='REGISTRADO'?'positive':'grey'" :label="p.value"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn v-if="can('Anular Movimientos')&&p.row.estado==='REGISTRADO'" dense flat round size="sm" color="negative" icon="cancel" @click="cancelMov(p.row)"><q-tooltip>Anular</q-tooltip></q-btn></q-td></template>
      </q-table>
    </q-card>

    <q-card v-show="tab==='ventas'" flat bordered>
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

    <!-- Ingreso / gasto de caja: todo se guarda en la tabla movimientos con tipo INGRESO | EGRESO -->
    <q-dialog v-model="movDialog"><q-card style="width:440px;max-width:95vw"><q-form @submit="saveMov">
      <q-card-section class="row items-center q-py-sm text-white" :class="mov.tipo==='INGRESO'?'bg-positive':'bg-negative'"><q-icon :name="mov.tipo==='INGRESO'?'add_card':'remove_shopping_cart'" size="22px" class="q-mr-sm"/><div class="text-subtitle1 text-weight-bold">{{mov.tipo==='INGRESO'?'Registrar ingreso':'Registrar gasto'}}</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section>
      <q-card-section class="q-gutter-sm">
        <q-btn-toggle v-model="mov.tipo" spread no-caps unelevated color="grey-2" text-color="grey-9" :toggle-color="mov.tipo==='INGRESO'?'positive':'negative'" :options="[{label:'Ingreso',value:'INGRESO',icon:'south_west'},{label:'Gasto / egreso',value:'EGRESO',icon:'north_east'}]" @update:model-value="mov.categoria=''"/>
        <q-input v-model="mov.concepto" v-uppercase dense outlined autofocus label="Concepto *" :placeholder="mov.tipo==='INGRESO'?'Ej.: VENTA DE ACEITE USADO':'Ej.: COMPRA DE PERIÓDICOS'" :rules="[v=>!!(v&&v.trim())||'Campo requerido']" hide-bottom-space/>
        <q-select v-model="mov.categoria" dense outlined label="Categoría" :options="catOptions" use-input new-value-mode="add-unique" hide-selected fill-input clearable input-debounce="0" @filter="filterCats" @input-value="v=>mov.categoria=(v||'').toUpperCase()"/>
        <q-input v-model.number="mov.monto" dense outlined type="number" min="0" step="any" inputmode="decimal" label="Monto Bs *" :rules="[v=>Number(v)>0||'Monto mayor a 0']" hide-bottom-space><template #prepend><q-icon name="payments"/></template></q-input>
        <q-btn-toggle v-model="mov.tipo_pago" spread no-caps unelevated toggle-color="secondary" color="grey-2" text-color="grey-9" :options="[{label:'Efectivo',value:'EFECTIVO',icon:'payments'},{label:'QR',value:'QR',icon:'qr_code_2'},{label:'Mixto',value:'COMBINADO',icon:'account_balance_wallet'}]"/>
        <div v-if="mov.tipo_pago==='COMBINADO'" class="row q-col-gutter-xs">
          <q-input v-model.number="mov.monto_efectivo" dense outlined type="number" min="0" step="any" inputmode="decimal" label="Efectivo Bs" class="col-6" :rules="[v=>(Number(v)||0)<=(Number(mov.monto)||0)||'Supera el monto']" hide-bottom-space/>
          <q-input :model-value="money(movQr)" dense outlined readonly label="QR Bs" class="col-6"/>
        </div>
        <q-input v-model="mov.observacion" dense outlined autogrow label="Observación"/>
      </q-card-section>
      <q-card-actions align="right"><q-btn flat no-caps label="Cancelar" v-close-popup/><q-btn unelevated no-caps :color="mov.tipo==='INGRESO'?'positive':'negative'" icon="save" label="Guardar" type="submit" :loading="movSaving"/></q-card-actions>
    </q-form></q-card></q-dialog>
  </q-page>
</template>

<script setup>
import {computed,getCurrentInstance,reactive,ref} from 'vue'
import {printKitchen,printSale} from '../../addons/ventaPrint'
// La pantalla abre con las ventas de hoy; limpiando las fechas se ve todo el historial.
const today=()=>{const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`}
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),search=ref(''),from=ref(today()),to=ref(today()),user=ref(null),payment=ref(null),dialog=ref(false),selected=reactive({}),summary=reactive({efectivo:0,qr:0,total:0,descuento:0,cantidad:0,usuarios:[]})
const pagination=ref({page:1,rowsPerPage:50,rowsNumber:0}),money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p),maxItems=4
const cards=computed(()=>[{label:'Total vendido',value:summary.total,money:true,icon:'trending_up',color:'#9f2321'},{label:`Pedidos · QR Bs ${money(summary.qr)}`,value:summary.cantidad,money:false,icon:'receipt_long',color:'#e9682f'},{label:'Otros ingresos',value:summary.ingresos,money:true,icon:'south_west',color:'#2e7d32'},{label:'Gastos',value:summary.egresos,money:true,icon:'north_east',color:'#c62828'},{label:'Neto (ventas + ingresos − gastos)',value:summary.neto,money:true,icon:'account_balance',color:'#6d4c41',strong:true},{label:'Efectivo en caja',value:summary.caja_efectivo,money:true,icon:'savings',color:'#f6a204',strong:true}])
// ── Ingresos y gastos de caja ──
const tab=ref('ventas'),movs=ref([]),movLoading=ref(false),movSearch=ref(''),movTipo=ref(null),movDialog=ref(false),movSaving=ref(false),cats=ref({INGRESO:[],EGRESO:[]}),catOptions=ref([])
const emptyMov=tipo=>({tipo,concepto:'',categoria:'',monto:null,tipo_pago:'EFECTIVO',monto_efectivo:null,observacion:''}),mov=reactive(emptyMov('EGRESO'))
// Mixto: se escribe el efectivo y el QR es lo que falta para el monto.
const movQr=computed(()=>Math.max(Math.round(((Number(mov.monto)||0)-(Number(mov.monto_efectivo)||0))*100)/100,0))
const movColumns=[{name:'actions',label:'',align:'left'},{name:'numero',label:'Número',field:'numero',align:'left'},{name:'fecha',label:'Fecha',field:r=>formatDate(r.fecha),align:'left'},{name:'tipo',label:'Tipo',field:'tipo',align:'center'},{name:'concepto',label:'Concepto',field:'concepto',align:'left'},{name:'categoria',label:'Categoría',field:'categoria',align:'left'},{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center',format:(v,r)=>v==='COMBINADO'?`MIXTO (Ef. ${money(r.monto_efectivo)} / QR ${money(r.monto_qr)})`:v},{name:'usuario',label:'Registró',field:'usuario_nombre',align:'left'},{name:'monto',label:'Monto',field:'monto',align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
function loadMovs(){if(!can('Ver Movimientos'))return;movLoading.value=true;proxy.$axios.get('/movimientos',{params:{q:movSearch.value,tipo:movTipo.value,desde:from.value,hasta:to.value,user_id:user.value?.id,per_page:0}}).then(r=>movs.value=r.data.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los ingresos y gastos')).finally(()=>movLoading.value=false)}
function openMov(tipo){Object.assign(mov,emptyMov(tipo));movDialog.value=true;proxy.$axios.get('/movimientos-catalogos').then(r=>cats.value=r.data.categorias).catch(()=>{})}
function filterCats(val,update){update(()=>{const q=(val||'').toUpperCase();catOptions.value=(cats.value[mov.tipo]||[]).filter(c=>c.includes(q))})}
async function saveMov(){movSaving.value=true;try{const{data}=await proxy.$axios.post('/movimientos',{...mov,categoria:mov.categoria||null,monto_efectivo:mov.tipo_pago==='COMBINADO'?(Number(mov.monto_efectivo)||0):null,monto_qr:mov.tipo_pago==='COMBINADO'?movQr.value:null});movDialog.value=false;proxy.$alert.success(`${data.tipo==='INGRESO'?'Ingreso':'Gasto'} ${data.numero} registrado: Bs ${money(data.monto)}`);loadSummary();loadMovs()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar')}finally{movSaving.value=false}}
function cancelMov(row){proxy.$alert.dialog(`¿Anular ${row.numero}?`,`${row.concepto} · Bs ${money(row.monto)}. Deja de sumar en caja y reportes.`).onOk(()=>proxy.$axios.put(`/movimientos/${row.id}/anular`).then(()=>{proxy.$alert.success('Registro anulado');loadSummary();loadMovs()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular')))}
const columns=[{name:'actions',label:'',align:'left'},{name:'ticket',label:'Pedido',field:'ticket',align:'left'},{name:'fecha',label:'Fecha',field:r=>formatDate(r.fecha),align:'left'},{name:'cliente',label:'Cliente',field:'cliente_nombre',align:'left'},{name:'usuario',label:'Vendedor',field:'usuario_nombre',align:'left'},{name:'detalles',label:'Productos',field:'detalles',align:'left'},{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'descuento',label:'Desc.',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
const detailColumns=[{name:'cantidad',label:'Cant.',field:'cantidad',align:'center'},{name:'nombre',label:'Producto',field:'nombre',align:'left'},{name:'precio',label:'Precio',field:'precio_venta',format:v=>`Bs ${money(v)}`,align:'right'},{name:'descuento',label:'Desc.',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'}]
const params=()=>({q:search.value,desde:from.value,hasta:to.value,user_id:user.value?.id,tipo_pago:payment.value}),paymentColor=v=>v==='EFECTIVO'?'green':v==='QR'?'blue':'purple'
const kitchenColor=v=>({PENDIENTE:'negative',PREPARANDO:'orange',LISTO:'accent',ENTREGADO:'positive'})[v]||'grey'
function setToday(){from.value=to.value=today();load()}
function clearDates(){from.value=to.value='';load()}
function formatDate(v){return v?new Date(v).toLocaleString('es-BO'):''}function load(){onRequest({pagination:{...pagination.value,page:1}});loadMovs()}function loadSummary(){proxy.$axios.get('/ventas-resumen',{params:params()}).then(r=>Object.assign(summary,r.data))}
function onRequest({pagination:p}){loading.value=true;proxy.$axios.get('/ventas',{params:{...params(),page:p.page,per_page:p.rowsPerPage}}).then(r=>{rows.value=r.data.data;pagination.value={...p,rowsNumber:r.data.total};loadSummary()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar las ventas')).finally(()=>loading.value=false)}
function showDetail(row){proxy.$axios.get(`/ventas/${row.id}`).then(r=>{Object.assign(selected,r.data);dialog.value=true})}function printRow(row,printer){proxy.$axios.get(`/ventas/${row.id}`).then(r=>printer(r.data))}
function cancel(row){proxy.$alert.dialog(`¿Anular la venta ${row.numero}?`,'El pedido también sale de la pantalla de cocina.').onOk(()=>proxy.$axios.put(`/ventas/${row.id}/anular`).then(()=>{proxy.$alert.success('Venta anulada');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular')))}
load()
</script>

<style scoped>.summary-card{border-radius:10px}.summary-card--strong{border-color:#e9682f;background:#fffaf5}.venta-productos{font-size:11px;line-height:13px;max-width:260px}</style>
