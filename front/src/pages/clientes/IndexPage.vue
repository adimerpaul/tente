<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Clientes</div><div class="text-caption text-grey-7">Clientes frecuentes y sus compras</div></div>
      <q-space/><q-btn v-if="can('Crear Clientes')" dense unelevated color="primary" icon="person_add" label="Nuevo" no-caps @click="openForm()"/>
    </div>
    <q-card flat bordered>
      <q-table dense flat :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[20,50,100]" @request="onRequest">
        <template #top><q-input v-model="search" dense outlined debounce="300" placeholder="Buscar nombre, CI/NIT o celular" clearable style="width:320px" @update:model-value="load"><template #prepend><q-icon name="search" size="18px"/></template></q-input></template>
        <template #body-cell-total="p"><q-td :props="p"><b>Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p">
          <template v-if="p.row.id!==1">
            <q-btn v-if="can('Editar Clientes')" dense flat round size="sm" icon="edit" color="primary" @click="openForm(p.row)"><q-tooltip>Editar</q-tooltip></q-btn>
            <q-btn v-if="can('Ver Ventas')" dense flat round size="sm" icon="receipt_long" color="blue-grey" @click="showSales(p.row)"><q-tooltip>Ver compras</q-tooltip></q-btn>
            <q-btn v-if="can('Eliminar Clientes')" dense flat round size="sm" icon="delete" color="negative" @click="remove(p.row)"><q-tooltip>Eliminar</q-tooltip></q-btn>
          </template>
          <q-badge v-else outline color="grey" label="Genérico"/>
        </q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog">
      <q-card style="width:460px;max-width:95vw">
        <q-form @submit="save">
          <q-card-section class="row items-center q-py-sm bg-orange-1"><q-icon name="person" color="primary" size="22px" class="q-mr-sm"/><div class="text-subtitle1 text-weight-bold">{{form.id?'Editar cliente':'Nuevo cliente'}}</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section>
          <q-card-section class="row q-col-gutter-sm">
            <q-input v-model="form.nombre" v-uppercase dense outlined autofocus label="Nombre *" class="col-12" :rules="[v=>!!v||'Campo requerido']" hide-bottom-space/>
            <q-input v-model="form.documento" dense outlined label="CI / NIT" class="col-6"/>
            <q-input v-model="form.celular" dense outlined label="Celular" class="col-6"/>
            <q-input v-model="form.direccion" dense outlined label="Dirección" class="col-12"/>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat no-caps label="Cancelar" v-close-popup/><q-btn unelevated no-caps color="primary" icon="save" label="Guardar" type="submit" :loading="saving"/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="salesDialog">
      <q-card style="width:640px;max-width:96vw">
        <q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">{{salesOf.nombre}}</div><div class="text-caption text-grey-7">Últimas compras</div></div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/>
        <q-table dense flat :rows="sales" :columns="salesColumns" row-key="id" :loading="loadingSales" hide-pagination :rows-per-page-options="[0]"/>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, reactive, ref } from 'vue'
const { proxy }=getCurrentInstance()
const rows=ref([]),loading=ref(false),saving=ref(false),dialog=ref(false),search=ref(''),pagination=ref({page:1,rowsPerPage:20,rowsNumber:0})
const salesDialog=ref(false),sales=ref([]),salesOf=ref({}),loadingSales=ref(false)
const empty=()=>({id:null,nombre:'',documento:'',celular:'',direccion:''}),form=reactive(empty())
const money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p)
const columns=[{name:'actions',label:'',align:'left'},{name:'nombre',label:'Nombre',field:'nombre',align:'left'},{name:'documento',label:'CI / NIT',field:'documento',align:'left'},{name:'celular',label:'Celular',field:'celular',align:'left'},{name:'direccion',label:'Dirección',field:'direccion',align:'left'},{name:'compras',label:'Compras',field:'ventas_count',align:'center'},{name:'total',label:'Total comprado',field:'ventas_sum_total',align:'right'}]
const salesColumns=[{name:'ticket',label:'Pedido',field:r=>`#${r.ticket}`,align:'left'},{name:'fecha',label:'Fecha',field:r=>new Date(r.fecha).toLocaleString('es-BO'),align:'left'},{name:'productos',label:'Productos',field:r=>(r.detalles||[]).map(d=>`${d.cantidad} ${d.nombre}`).join(', '),align:'left',style:'white-space:normal'},{name:'total',label:'Total',field:r=>`Bs ${money(r.total)}`,align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
function load(){onRequest({pagination:{...pagination.value,page:1}})}
function onRequest({pagination:p}){loading.value=true;proxy.$axios.get('/clientes',{params:{q:search.value,page:p.page,per_page:p.rowsPerPage}}).then(r=>{rows.value=r.data.data;pagination.value={...p,rowsNumber:r.data.total}}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los clientes')).finally(()=>loading.value=false)}
function openForm(row=null){Object.assign(form,empty(),row||{});dialog.value=true}
async function save(){saving.value=true;try{if(form.id)await proxy.$axios.put(`/clientes/${form.id}`,form);else await proxy.$axios.post('/clientes',form);proxy.$alert.success('Cliente guardado');dialog.value=false;load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{saving.value=false}}
function remove(c){proxy.$alert.dialog(`¿Eliminar al cliente ${c.nombre}?`,'Sus ventas se conservan con el nombre registrado.').onOk(()=>proxy.$axios.delete(`/clientes/${c.id}`).then(()=>{proxy.$alert.success('Cliente eliminado');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')))}
function showSales(c){salesOf.value=c;sales.value=[];salesDialog.value=true;loadingSales.value=true;proxy.$axios.get('/ventas',{params:{cliente_id:c.id,per_page:30}}).then(r=>sales.value=r.data.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar las compras')).finally(()=>loadingSales.value=false)}
load()
</script>
