<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Productos</div><div class="text-caption text-grey-7">Menú de Tenté · lo que no está disponible no aparece al vender</div></div>
      <q-space/><q-btn v-if="can('Crear Productos')" dense unelevated color="primary" icon="add" label="Nuevo plato" no-caps @click="openForm()"/>
    </div>
    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm items-center">
        <q-input v-model="search" dense outlined debounce="300" clearable placeholder="Buscar nombre o código" class="col-12 col-sm-5"><template #prepend><q-icon name="search"/></template></q-input>
        <div class="col row q-gutter-xs"><q-chip v-for="c in categories" :key="c" clickable dense :color="category===c?'primary':'grey-3'" :text-color="category===c?'white':'grey-9'" @click="category=c">{{c}}</q-chip></div>
      </q-card-section>
      <q-separator/>
      <q-card-section class="q-pa-sm">
        <q-inner-loading :showing="loading"/>
        <div class="product-grid">
          <q-card v-for="p in filtered" :key="p.id" flat bordered class="product" :class="{'product--off':!p.disponible}">
            <div class="product__photo" @click="can('Editar Productos')&&pickPhoto(p)">
              <img v-if="p.foto" :src="photo(p.foto)" :alt="p.nombre"/><q-icon v-else name="lunch_dining" size="56px" color="orange-3"/>
              <div v-if="can('Editar Productos')" class="product__overlay"><q-icon name="photo_camera"/> Cambiar foto</div>
              <q-badge v-if="!p.disponible" color="negative" label="NO DISPONIBLE" class="product__badge"/>
            </div>
            <q-card-section class="q-pa-sm">
              <div class="row items-start no-wrap"><div class="col"><div class="text-caption text-grey-6">{{p.codigo}} · {{p.categoria||'SIN CATEGORÍA'}}</div><div class="text-weight-bold product__name">{{p.nombre}}</div></div><div class="product__price">Bs {{money(p.precio_venta)}}</div></div>
              <div class="text-caption text-grey-7 ellipsis-2-lines">{{p.descripcion}}</div>
            </q-card-section>
            <q-separator/>
            <q-card-actions class="q-pa-xs">
              <q-toggle v-if="can('Editar Productos')" :model-value="p.disponible" dense size="sm" color="positive" label="Disponible" @update:model-value="toggle(p)"/>
              <q-space/>
              <q-btn v-if="can('Editar Productos')" dense flat round size="sm" icon="edit" color="primary" @click="openForm(p)"/>
              <q-btn v-if="can('Eliminar Productos')" dense flat round size="sm" icon="delete" color="negative" @click="remove(p)"/>
            </q-card-actions>
          </q-card>
        </div>
        <div v-if="!loading&&!filtered.length" class="text-center text-grey-6 q-pa-lg">No hay productos</div>
      </q-card-section>
    </q-card>
    <q-file ref="photoInput" v-model="photoFile" class="hidden" accept="image/*" :max-file-size="8388608" @update:model-value="uploadPhoto" @rejected="()=>proxy.$alert.error('Selecciona una imagen de hasta 8 MB')"/>

    <q-dialog v-model="dialog">
      <q-card style="width:520px;max-width:95vw">
        <q-form @submit="save">
          <q-card-section class="row items-center q-py-sm bg-orange-1"><q-icon name="lunch_dining" color="primary" size="22px" class="q-mr-sm"/><div class="text-subtitle1 text-weight-bold">{{form.id?'Editar plato':'Nuevo plato'}}</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section>
          <q-card-section class="row q-col-gutter-sm">
            <q-input v-model="form.codigo" v-uppercase dense outlined label="Código *" class="col-4" :rules="[v=>!!v||'Requerido']" hide-bottom-space/>
            <q-input v-model="form.nombre" v-uppercase dense outlined label="Nombre *" class="col-8" :rules="[v=>!!v||'Requerido']" hide-bottom-space/>
            <q-select v-model="form.categoria" dense outlined label="Categoría" class="col-6" :options="categoryOptions" use-input new-value-mode="add-unique" hide-selected fill-input input-debounce="0" @input-value="v=>form.categoria=v?.toUpperCase()"/>
            <q-input v-model.number="form.precio_venta" dense outlined type="number" min="0" step="0.5" label="Precio Bs *" class="col-3" :rules="[v=>v!==''&&v>=0||'Requerido']" hide-bottom-space/>
            <q-input v-model.number="form.orden" dense outlined type="number" min="0" label="Orden" class="col-3"/>
            <q-input v-model="form.descripcion" dense outlined label="Descripción" class="col-12"/>
            <q-toggle v-model="form.disponible" label="Disponible para la venta" color="positive" class="col-12"/>
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
const rows=ref([]),loading=ref(false),saving=ref(false),dialog=ref(false),search=ref(''),category=ref('TODOS'),photoFile=ref(null),photoInput=ref(null),photoTarget=ref(null)
const empty=()=>({id:null,codigo:'',nombre:'',categoria:'',precio_venta:0,descripcion:'',orden:null,disponible:true}),form=reactive(empty())
const money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p),photo=f=>`${proxy.$imgBase}/images/${f}`
const categoryOptions=computed(()=>[...new Set(rows.value.map(p=>p.categoria).filter(Boolean))])
const categories=computed(()=>['TODOS',...categoryOptions.value])
const filtered=computed(()=>{const q=(search.value||'').toUpperCase();return rows.value.filter(p=>(category.value==='TODOS'||p.categoria===category.value)&&(!q||p.nombre.includes(q)||p.codigo.includes(q)))})
function load(){loading.value=true;proxy.$axios.get('/productos',{params:{per_page:0}}).then(r=>rows.value=r.data.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los productos')).finally(()=>loading.value=false)}
function nextCode(){const n=rows.value.map(p=>Number(String(p.codigo).replace(/\D/g,''))||0);return `TEN-${String(Math.max(0,...n)+1).padStart(3,'0')}`}
function openForm(row=null){Object.assign(form,empty(),row?{...row,precio_venta:Number(row.precio_venta)}:{codigo:nextCode()});dialog.value=true}
async function save(){saving.value=true;try{if(form.id)await proxy.$axios.put(`/productos/${form.id}`,form);else await proxy.$axios.post('/productos',form);proxy.$alert.success('Plato guardado');dialog.value=false;load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{saving.value=false}}
function toggle(p){proxy.$axios.patch(`/productos/${p.id}/disponible`).then(r=>{p.disponible=r.data.disponible}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo actualizar'))}
function remove(p){proxy.$alert.dialog(`¿Eliminar ${p.nombre}?`,'Las ventas anteriores conservan el nombre y precio vendidos.').onOk(()=>proxy.$axios.delete(`/productos/${p.id}`).then(()=>{proxy.$alert.success('Plato eliminado');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')))}
function pickPhoto(p){photoTarget.value=p;photoInput.value?.pickFiles()}
function uploadPhoto(file){if(!file||!photoTarget.value)return;const data=new FormData();data.append('foto',file);proxy.$axios.post(`/productos/${photoTarget.value.id}/foto`,data).then(r=>{Object.assign(photoTarget.value,r.data);proxy.$alert.success('Foto actualizada')}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo subir la foto')).finally(()=>{photoFile.value=null})}
onMounted(load)
</script>

<style scoped>
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}.product{border-radius:12px;overflow:hidden}.product--off{opacity:.6}
.product__photo{position:relative;height:130px;background:#fdf0e7;display:flex;align-items:center;justify-content:center;cursor:pointer}.product__photo img{width:100%;height:100%;object-fit:cover}
.product__overlay{position:absolute;inset:auto 0 0;background:rgba(159,35,33,.8);color:#fff;font-size:11px;text-align:center;padding:3px;opacity:0;transition:.2s}.product__photo:hover .product__overlay{opacity:1}.product__badge{position:absolute;top:6px;left:6px}
.product__name{font-size:13px;line-height:1.2}.product__price{font-size:16px;font-weight:800;color:#e9682f;white-space:nowrap;margin-left:6px}
</style>
