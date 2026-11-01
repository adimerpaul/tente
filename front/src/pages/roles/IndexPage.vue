<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Roles y permisos</div><div class="text-caption text-grey-7">Qué puede hacer cada tipo de usuario: administrador, vendedor, cocinero…</div></div>
      <q-space/><q-btn dense unelevated color="primary" icon="add" label="Nuevo rol" no-caps @click="openForm()"/>
    </div>
    <q-inner-loading :showing="loading"/>
    <div class="roles-grid">
      <q-card v-for="r in roles" :key="r.id" flat bordered class="role">
        <q-card-section class="row items-center q-py-sm role__head" :style="{background:color(r.name)}">
          <q-icon :name="icon(r.name)" size="26px" color="white"/>
          <div class="q-ml-sm col"><div class="text-subtitle1 text-weight-bold text-white">{{r.name}}</div><div class="text-caption text-white" style="opacity:.85">{{r.users_count}} usuario(s) · {{r.permissions.length}} permisos</div></div>
          <q-btn dense flat round icon="edit" color="white" @click="openForm(r)"/>
          <q-btn v-if="r.name!=='Administrador'" dense flat round icon="delete" color="white" @click="remove(r)"/>
        </q-card-section>
        <q-card-section class="q-pa-sm">
          <div v-for="g in groupsOf(r.permissions)" :key="g.name" class="q-mb-xs"><div class="text-caption text-weight-bold text-secondary">{{g.name}}</div><q-chip v-for="p in g.items" :key="p.id" dense square size="sm" color="orange-1" text-color="brown-9">{{p.name}}</q-chip></div>
          <div v-if="!r.permissions.length" class="text-grey-6 text-caption">Sin permisos</div>
        </q-card-section>
      </q-card>
    </div>

    <q-dialog v-model="dialog" persistent>
      <q-card style="width:760px;max-width:96vw">
        <q-form @submit="save">
          <q-card-section class="row items-center q-py-sm bg-orange-1"><q-icon name="admin_panel_settings" color="primary" size="22px" class="q-mr-sm"/><div class="text-subtitle1 text-weight-bold">{{form.id?'Editar rol':'Nuevo rol'}}</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section>
          <q-card-section class="q-pa-sm">
            <q-input v-model="form.name" dense outlined label="Nombre del rol *" :disable="form.name==='Administrador'&&!!form.id" :rules="[v=>!!v||'Campo requerido']" hide-bottom-space style="max-width:320px"/>
            <q-banner v-if="form.name==='Administrador'&&form.id" dense rounded class="bg-orange-1 text-brown-9 q-mt-sm">El rol Administrador siempre tiene todos los permisos.</q-banner>
            <div class="row items-center q-mt-sm"><div class="text-weight-bold">Permisos</div><q-space/><q-btn dense flat size="sm" no-caps label="Todos" @click="form.permissions=permissions.map(p=>p.id)"/><q-btn dense flat size="sm" no-caps label="Ninguno" @click="form.permissions=[]"/></div>
            <div class="permissions-grid">
              <div v-for="g in groups" :key="g.name" class="permission-group">
                <div class="text-caption text-weight-bold text-primary">{{g.name}}</div>
                <q-checkbox v-for="p in g.items" :key="p.id" v-model="form.permissions" :val="p.id" :label="p.name" dense size="sm" :disable="form.name==='Administrador'&&!!form.id"/>
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat no-caps label="Cancelar" v-close-popup/><q-btn unelevated no-caps color="primary" icon="save" label="Guardar" type="submit" :loading="saving"/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
const { proxy }=getCurrentInstance()
const roles=ref([]),permissions=ref([]),loading=ref(false),saving=ref(false),dialog=ref(false),form=reactive({id:null,name:'',permissions:[]})
const color=n=>({Administrador:'#9f2321',Vendedor:'#e9682f',Cocinero:'#f6a204'})[n]||'#6d4c41',icon=n=>({Administrador:'shield',Vendedor:'point_of_sale',Cocinero:'soup_kitchen'})[n]||'badge'
// Los grupos salen de la columna "grupo" de la tabla permissions, ya ordenados por el backend.
function group(list){const out=[];for(const p of list){const name=p.grupo||'Otros';let g=out.find(x=>x.name===name);if(!g)out.push(g={name,items:[]});g.items.push(p)}return out}
const groups=computed(()=>group(permissions.value)),groupsOf=ids=>group(permissions.value.filter(p=>ids.includes(p.id)))
async function load(){loading.value=true;try{const[r,p]=await Promise.all([proxy.$axios.get('/roles'),proxy.$axios.get('/permissions')]);roles.value=r.data;permissions.value=p.data}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los roles')}finally{loading.value=false}}
function openForm(r=null){Object.assign(form,{id:r?.id||null,name:r?.name||'',permissions:[...(r?.permissions||[])]});dialog.value=true}
async function save(){saving.value=true;try{if(form.id)await proxy.$axios.put(`/roles/${form.id}`,form);else await proxy.$axios.post('/roles',form);proxy.$alert.success('Rol guardado','Los usuarios con este rol verán el cambio al volver a entrar.');dialog.value=false;load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{saving.value=false}}
function remove(r){proxy.$alert.dialog(`¿Eliminar el rol ${r.name}?`).onOk(()=>proxy.$axios.delete(`/roles/${r.id}`).then(()=>{proxy.$alert.success('Rol eliminado');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')))}
onMounted(load)
</script>

<style scoped>
.roles-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px}.role{border-radius:12px;overflow:hidden}
.permissions-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-top:5px}.permission-group{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 5px;padding:6px;border:1px solid #f1d9c8;border-radius:7px;background:#fffaf6}.permission-group>.text-caption{grid-column:1/-1}
@media(max-width:600px){.permissions-grid,.permission-group{grid-template-columns:1fr}}
</style>
