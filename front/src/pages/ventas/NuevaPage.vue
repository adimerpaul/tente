<template>
  <q-page class="q-pa-sm">
    <div class="row q-col-gutter-sm">
      <!-- MENÚ -->
      <div class="col-12 col-md-7 col-lg-8">
        <q-card flat bordered>
          <q-card-section class="q-pa-sm">
            <div class="row items-center q-col-gutter-sm">
              <q-input v-model="search" dense outlined clearable class="col-12 col-sm" placeholder="Buscar plato"><template #prepend><q-icon name="search"/></template></q-input>
              <div class="col-auto"><q-btn dense flat round icon="refresh" color="primary" :loading="loading" @click="loadProducts"><q-tooltip>Actualizar menú</q-tooltip></q-btn></div>
            </div>
            <div class="row q-gutter-xs q-mt-xs">
              <q-chip v-for="c in categories" :key="c" clickable :icon="categoriaIcon(c)" :color="category===c?'primary':'grey-3'" :text-color="category===c?'white':'grey-9'" @click="category=c">{{c}}<q-badge rounded class="q-ml-xs" :color="category===c?'white':'grey-5'" :text-color="category===c?'primary':'white'" :label="categoryCount(c)"/></q-chip>
            </div>
          </q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-sm">
            <div class="menu-grid">
              <div v-for="p in visibleProducts" :key="p.id" class="menu-item" v-ripple @click="add(p)">
                <div class="menu-item__photo"><img v-if="p.foto" :src="photo(p.foto)" :alt="p.nombre"/><q-icon v-else name="lunch_dining" size="48px" color="orange-3"/>
                  <q-badge v-if="qtyInCart(p.id)" floating rounded color="secondary" :label="qtyInCart(p.id)" class="menu-item__qty"/></div>
                <div class="menu-item__body"><div class="menu-item__name">{{p.nombre}}</div><div class="menu-item__price">Bs {{money(p.precio_venta)}}</div></div>
              </div>
            </div>
            <div v-if="!loading&&!visibleProducts.length" class="text-center text-grey-6 q-pa-lg">No hay platos para mostrar</div>
          </q-card-section>
        </q-card>
      </div>

      <!-- PEDIDO -->
      <div class="col-12 col-md-5 col-lg-4">
        <q-card flat bordered class="order-card">
          <q-tabs v-model="activeCart" dense no-caps align="justify" active-color="white" active-bg-color="secondary" indicator-color="accent" class="text-grey-8 bg-orange-1">
            <q-tab v-for="(c,i) in $store.carts" :key="i" :name="i" class="q-px-xs"><div class="text-caption text-weight-bold">Pedido {{i+1}}</div><div v-if="c.items.length" style="font-size:10px">Bs {{money($store.cartTotal(i))}}</div></q-tab>
          </q-tabs>
          <q-card-section class="q-pa-sm">
            <q-select :model-value="cart.cliente_nombre" dense outlined use-input hide-selected fill-input clearable input-debounce="300" input-class="text-uppercase" label="Cliente (nombre para llamar el pedido)"
              :options="clientOptions" option-label="nombre" @filter="filterClients" @input-value="setClientName" @update:model-value="pickClient">
              <template #prepend><q-icon :name="cart.cliente?'how_to_reg':'person_add'" :color="cart.cliente?'positive':'grey-6'" size="20px"><q-tooltip>{{cart.cliente?'Cliente registrado':'Si no existe, se registra al cobrar'}}</q-tooltip></q-icon></template>
              <template #option="s"><q-item v-bind="s.itemProps"><q-item-section><q-item-label>{{s.opt.nombre}}</q-item-label><q-item-label v-if="s.opt.documento||s.opt.celular" caption>{{[s.opt.documento,s.opt.celular].filter(Boolean).join(' · ')}}</q-item-label></q-item-section></q-item></template>
              <template #no-option="{inputValue}"><q-item v-if="inputValue"><q-item-section class="text-grey-7">Cliente nuevo: «{{inputValue.toUpperCase()}}» se registra al cobrar</q-item-section></q-item></template>
            </q-select>
          </q-card-section>
          <q-separator/>
          <q-list separator class="order-items">
            <q-item v-for="(item,index) in cart.items" :key="index" class="q-px-sm">
              <q-item-section avatar><q-avatar rounded size="42px"><img v-if="item.foto" :src="photo(item.foto)"/><q-icon v-else name="lunch_dining"/></q-avatar></q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-bold text-caption">{{item.nombre}}</q-item-label>
                <q-item-label caption>Bs {{money(item.precio_venta)}} c/u · <b class="text-secondary">Bs {{money(item.precio_venta*item.cantidad)}}</b></q-item-label>
                <q-input v-model="item.observacion" v-uppercase dense borderless placeholder="+ nota (sin cebolla, extra salsa…)" class="note-input"/>
              </q-item-section>
              <q-item-section side>
                <div class="row items-center no-wrap"><q-btn dense round flat size="sm" icon="remove" color="secondary" @click="decrease(index)"/><div class="qty">{{item.cantidad}}</div><q-btn dense round flat size="sm" icon="add" color="primary" @click="item.cantidad++"/></div>
              </q-item-section>
            </q-item>
            <q-item v-if="!cart.items.length"><q-item-section class="text-center text-grey-6 q-py-lg"><q-icon name="touch_app" size="36px" color="orange-3" class="q-mx-auto"/>Toca un plato del menú para agregarlo</q-item-section></q-item>
          </q-list>
          <q-separator/>
          <q-card-section class="q-pa-sm">
            <div class="row q-col-gutter-xs">
              <q-input v-model.number="cart.descuento" dense outlined type="number" min="0" step="0.5" label="Descuento Bs" class="col-5"/>
              <q-input v-model="cart.observacion" dense outlined label="Observación" class="col-7"/>
            </div>
            <div class="row items-center q-mt-sm total-row"><span>TOTAL</span><q-space/><b>Bs {{money(total)}}</b></div>
            <q-btn-toggle v-model="tipoPago" spread no-caps unelevated toggle-color="secondary" color="grey-2" text-color="grey-9" class="q-mt-sm"
              :options="[{label:'Efectivo',value:'EFECTIVO',icon:'payments'},{label:'QR',value:'QR',icon:'qr_code_2'},{label:'Mixto',value:'COMBINADO',icon:'account_balance_wallet'}]"/>
            <div v-if="tipoPago==='EFECTIVO'" class="row q-col-gutter-xs q-mt-xs items-center">
              <q-input v-model.number="recibido" dense outlined type="number" min="0" label="Recibido Bs" class="col-6"/>
              <div class="col-6 text-right"><div class="text-caption text-grey-7">Cambio</div><div class="text-h6 text-weight-bold" :class="cambio<0?'text-negative':'text-positive'">Bs {{money(Math.max(cambio,0))}}</div></div>
              <div class="col-12 row q-gutter-xs"><q-btn v-for="b in [10,20,50,100,200]" :key="b" dense outline size="sm" color="primary" :label="b" @click="recibido=b"/><q-btn dense outline size="sm" color="grey-8" label="Exacto" @click="recibido=total"/></div>
            </div>
            <div v-if="tipoPago==='COMBINADO'" class="row q-col-gutter-xs q-mt-xs">
              <q-input v-model.number="efectivo" dense outlined type="number" min="0" label="Efectivo Bs" class="col-6"/>
              <q-input :model-value="money(Math.max(total-(Number(efectivo)||0),0))" dense outlined readonly label="QR Bs" class="col-6"/>
            </div>
            <div class="row q-col-gutter-xs q-mt-sm">
              <div class="col-4"><q-btn class="full-width" outline color="grey-8" icon="delete_sweep" label="Vaciar" no-caps :disable="!cart.items.length" @click="clearCart"/></div>
              <div class="col-8"><q-btn class="full-width" unelevated color="primary" icon="check_circle" :label="`Cobrar Bs ${money(total)}`" no-caps size="md" :loading="saving" :disable="!cart.items.length" @click="checkout"/></div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue'
import { printKitchen, printSale } from '../../addons/ventaPrint'
import { categoriaIcon } from '../../addons/categoriaIcon'
const { proxy } = getCurrentInstance()
const products=ref([]),loading=ref(false),saving=ref(false),search=ref(''),category=ref('TODOS'),tipoPago=ref('EFECTIVO'),recibido=ref(null),efectivo=ref(null)
const clientOptions=ref([])
const money=v=>Number(v||0).toFixed(2),photo=f=>`${proxy.$imgBase}/images/${f}`
const activeCart=computed({get:()=>proxy.$store.activeCart,set:v=>proxy.$store.setActiveCart(v)}),cart=computed(()=>proxy.$store.currentCart)
const total=computed(()=>proxy.$store.cartTotal(proxy.$store.activeCart)),cambio=computed(()=>(Number(recibido.value)||0)-total.value)
const categories=computed(()=>['TODOS',...new Set(products.value.map(p=>p.categoria).filter(Boolean))]),categoryCount=c=>c==='TODOS'?products.value.length:products.value.filter(p=>p.categoria===c).length
const visibleProducts=computed(()=>{const q=(search.value||'').toUpperCase();return products.value.filter(p=>(category.value==='TODOS'||p.categoria===category.value)&&(!q||p.nombre.includes(q)||p.codigo.includes(q)))})
const qtyInCart=id=>cart.value.items.filter(i=>i.producto_id===id).reduce((s,i)=>s+i.cantidad,0)
watch(activeCart,()=>{recibido.value=null;efectivo.value=null})
function loadProducts(){loading.value=true;proxy.$axios.get('/productos',{params:{per_page:0,disponibles:1}}).then(r=>products.value=r.data.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el menú')).finally(()=>loading.value=false)}
// Un mismo plato se agrupa en una línea salvo que esa línea ya tenga una nota distinta.
function add(p){const line=cart.value.items.find(i=>i.producto_id===p.id&&!i.observacion);if(line)line.cantidad++;else cart.value.items.push({producto_id:p.id,nombre:p.nombre,precio_venta:Number(p.precio_venta),foto:p.foto,cantidad:1,observacion:''})}
function decrease(index){const item=cart.value.items[index];if(item.cantidad>1)item.cantidad--;else cart.value.items.splice(index,1)}
function clearCart(){proxy.$alert.dialog('¿Vaciar este pedido?').onOk(()=>proxy.$store.resetCart())}
function filterClients(val,update){proxy.$axios.get('/clientes',{params:{q:val,per_page:20}}).then(r=>update(()=>{clientOptions.value=r.data.data.filter(c=>c.id!==1)})).catch(()=>update(()=>{clientOptions.value=[]}))}
// Un solo campo: se elige un cliente existente o se escribe un nombre nuevo, que el backend busca o registra al cobrar.
function setClientName(v){cart.value.cliente_nombre=v||'';if(cart.value.cliente&&cart.value.cliente.nombre!==(v||'').trim().toUpperCase())cart.value.cliente=null}
function pickClient(c){if(c&&typeof c==='object'){cart.value.cliente=c;cart.value.cliente_nombre=c.nombre}else{cart.value.cliente=null;cart.value.cliente_nombre=c||''}}
async function checkout(){
  const discount=Number(cart.value.descuento)||0
  if(discount<0)return proxy.$alert.error('El descuento no puede ser negativo')
  if(tipoPago.value==='EFECTIVO'&&recibido.value&&cambio.value<0)return proxy.$alert.error('El monto recibido no alcanza para el total')
  const cash=tipoPago.value==='COMBINADO'?Math.min(Number(efectivo.value)||0,total.value):null
  saving.value=true
  try{
    const {data}=await proxy.$axios.post('/ventas',{cliente_id:cart.value.cliente?.id||null,cliente_nombre:cart.value.cliente?null:(cart.value.cliente_nombre||'').trim()||null,tipo_consumo:'LLEVAR',descuento:discount,observacion:cart.value.observacion,tipo_pago:tipoPago.value,monto_efectivo:cash,monto_qr:cash===null?null:Math.round((total.value-cash)*100)/100,detalles:cart.value.items.map(i=>({producto_id:i.producto_id,cantidad:i.cantidad,observacion:i.observacion||null}))})
    if(tipoPago.value==='EFECTIVO'&&recibido.value)data.recibido=Number(recibido.value)
    proxy.$store.resetCart();recibido.value=null;efectivo.value=null;tipoPago.value='EFECTIVO'
    proxy.$alert.choose(`Pedido #${data.ticket} registrado`,`${data.numero} · Total Bs ${money(data.total)}${data.recibido?` · Cambio Bs ${money(data.recibido-data.total)}`:''}`,[{label:'Ticket del cliente',value:'cliente'},{label:'Comanda de cocina',value:'cocina'}],['cliente','cocina'],'Imprimir','No imprimir')
      .onOk(sel=>{if(sel.includes('cliente'))printSale(data);if(sel.includes('cocina'))setTimeout(()=>printKitchen(data),sel.includes('cliente')?800:0)})
  }catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar la venta')}finally{saving.value=false}
}
onMounted(loadProducts)
</script>

<style scoped>
.menu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}.menu-item{border:1px solid #f1d9c8;border-radius:12px;overflow:hidden;background:#fff;cursor:pointer;transition:.15s;position:relative;user-select:none}.menu-item:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(159,35,33,.15);border-color:#e9682f}.menu-item:active{transform:scale(.97)}
.menu-item__photo{height:100px;background:#fdf0e7;display:flex;align-items:center;justify-content:center;position:relative}.menu-item__photo img{width:100%;height:100%;object-fit:cover}.menu-item__qty{top:6px;right:6px;font-size:13px;padding:4px 8px}
.menu-item__body{padding:6px 8px}.menu-item__name{font-size:12px;font-weight:700;line-height:1.15;min-height:28px;color:#3b2320}.menu-item__price{font-size:15px;font-weight:800;color:#e9682f}
.order-card{position:sticky;top:62px}.order-items{max-height:38vh;overflow:auto}.qty{min-width:26px;text-align:center;font-weight:800;font-size:15px}.note-input :deep(.q-field__control){height:22px;min-height:22px}.note-input :deep(input){font-size:11px;color:#9f2321}
.total-row{font-size:20px;color:#9f2321;border-top:2px dashed #f1d9c8;padding-top:6px}
@media(max-width:600px){.menu-grid{grid-template-columns:repeat(2,1fr)}.order-card{position:static}}
</style>
