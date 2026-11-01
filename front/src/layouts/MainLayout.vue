<template>
  <q-layout view="lHh Lpr lFf">
    <!-- HEADER -->
    <q-header class="app-header">
      <q-toolbar>
        <q-btn
          flat
          color="primary"
          :icon="leftDrawerOpen ? 'keyboard_double_arrow_left' : 'keyboard_double_arrow_right'"
          aria-label="Menu"
          @click="toggleLeftDrawer"
          unelevated
          dense
        />
        <div class="row items-center q-gutter-sm">
          <div class="text-subtitle1 text-weight-bold text-secondary" style="line-height: 0.9">
            {{ companyName }}
          </div>
          <q-chip v-if="offline" dense square color="negative" text-color="white" icon="wifi_off" label="Sin conexión" />
        </div>

        <q-space />

        <q-btn-dropdown flat unelevated no-caps dropdown-icon="expand_more">
          <template v-slot:label>
            <div class="header-user row items-center no-wrap">
              <q-avatar rounded size="30px" style="border:2px solid #f6d3bd">
                <img :src="$store.user.avatar ? $imgBase + '/images/' + $store.user.avatar : $imgBase + '/images/default.png'"
                     style="object-fit:cover;width:100%;height:100%"
                     @error="$event.target.src = $imgBase + '/images/default.png'" />
              </q-avatar>
              <div class="text-left" style="line-height: 1">
                <div class="ellipsis" style="max-width: 130px;">{{ $store.user.username }}</div>
                <div class="text-caption text-grey-7" style="font-size:10px">{{ $store.user.rol }}</div>
              </div>
            </div>
          </template>

          <q-separator />

          <q-item clickable v-ripple @click="logout" v-close-popup>
            <q-item-section avatar>
              <q-icon name="logout" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </q-btn-dropdown>
      </q-toolbar>
    </q-header>

    <!-- DRAWER -->
    <q-drawer
      v-model="leftDrawerOpen"
      bordered
      show-if-above
      :width="200"
      :breakpoint="700"
      class="app-drawer text-white"
    >
      <q-scroll-area class="fit">
        <div class="drawer-shell">
          <div class="drawer-brand">
            <img src="/logo-tente-blanco.png" :alt="companyName" />
          </div>

          <div class="drawer-eyebrow">Módulos</div>

          <q-list dense class="drawer-menu">
            <q-item
              v-for="link in visibleLinks"
              :key="link.title"
              dense
              clickable
              :to="link.link"
              exact
              class="drawer-menu-link"
              active-class="drawer-menu-link--active"
            >
              <q-item-section avatar class="drawer-menu-link__avatar">
                <q-icon :name="link.icon" size="18px" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="drawer-menu-link__label" lines="1">{{ link.title }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>

          <div class="drawer-footer">
            <div>Tenté v{{ $version }}</div>
            <div>© {{ new Date().getFullYear() }} Tenté · Sabor y Sazón</div>
          </div>

          <q-item clickable class="drawer-logout" @click="logout">
            <q-item-section avatar class="drawer-menu-link__avatar">
              <q-icon name="logout" size="18px" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </div>
      </q-scroll-area>
    </q-drawer>

    <!-- PAGE -->
    <q-page-container class="page-bg">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, onUnmounted, ref } from 'vue'
import { clearSession } from '../addons/sesion'
import { companyData } from '../addons/empresa'

const { proxy } = getCurrentInstance()

const leftDrawerOpen = ref(false)
const companyName = ref(companyData().nombre_empresa || 'Tenté')

// Aviso de red: el navegador sin red o una llamada al API sin respuesta (navigator.onLine solo no es confiable).
const sinRed = ref(!navigator.onLine)
const offline = computed(() => proxy.$store.offline || sinRed.value)
const marcarEstadoRed = () => { sinRed.value = !navigator.onLine }

const links = [
  { title: 'Inicio', icon: 'dashboard', link: '/', can: null },
  { title: 'Nueva venta', icon: 'point_of_sale', link: '/ventas/nueva', can: 'Crear Ventas' },
  { title: 'Ventas', icon: 'receipt_long', link: '/ventas', can: 'Ver Ventas' },
  { title: 'Cocina', icon: 'soup_kitchen', link: '/cocina', can: 'Ver Cocina' },
  { title: 'Clientes', icon: 'groups', link: '/clientes', can: 'Ver Clientes' },
  { title: 'Productos', icon: 'lunch_dining', link: '/productos', can: 'Ver Productos' },
  { title: 'Reportes', icon: 'insights', link: '/reportes', can: 'Ver Reportes' },
  { title: 'Usuarios', icon: 'people', link: '/usuarios', can: 'Ver Usuarios' },
  { title: 'Roles y permisos', icon: 'admin_panel_settings', link: '/roles', can: 'Gestionar Roles' },
  { title: 'Configuración', icon: 'settings', link: '/configuracion', can: 'Gestionar Configuración' },
]

// `can` puede ser un permiso o una lista: con cualquiera de ellos el ítem se muestra.
const visibleLinks = computed(() => links.filter(link => link.can === null || proxy.$store.hasPermission(link.can)))

function toggleLeftDrawer () {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

onMounted(() => {
  window.addEventListener('online', marcarEstadoRed)
  window.addEventListener('offline', marcarEstadoRed)
  proxy.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${proxy.$imgBase}/images/${data.logo}` : null
    companyName.value = data.nombre_empresa || 'Tenté'
    localStorage.setItem('empresaTente', JSON.stringify(data))
  }).catch(() => { /* sin conexión: se usa lo guardado */ })
})

onUnmounted(() => {
  window.removeEventListener('online', marcarEstadoRed)
  window.removeEventListener('offline', marcarEstadoRed)
})

function logout () {
  const pendientes = proxy.$store.pendingCarts
  proxy.$alert.dialog('¿Desea salir del sistema?', pendientes ? `Hay ${pendientes} pedido(s) sin cobrar; se perderán al salir.` : '').onOk(() => {
    proxy.$axios.post('/logout').finally(() => {
      proxy.$store.logout()
      clearSession()
      delete proxy.$axios.defaults.headers.common['Authorization']
      proxy.$router.push('/login')
    })
  })
}
</script>

<style>
.app-drawer,
.app-drawer .q-drawer__content,
.app-drawer .q-scrollarea,
.app-drawer .q-scrollarea__container,
.app-drawer .q-scrollarea__content {
  background: linear-gradient(180deg, #9f2321 0%, #7d1a18 60%, #5c1311 100%) !important;
  color: #ffffff;
}

.drawer-shell {
  min-height: 100%;
  padding: 8px 6px;
}

.drawer-brand {
  display: flex;
  justify-content: center;
  padding: 6px 4px 10px;
  margin-bottom: 4px;
  border-bottom: 3px solid #f6a204;
}

.drawer-brand img {
  width: 150px;
  max-width: 100%;
  object-fit: contain;
}

.drawer-eyebrow {
  padding: 4px 8px;
  color: rgba(255, 255, 255, 0.7);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.drawer-menu {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.drawer-menu-link {
  min-height: 34px;
  padding: 0 8px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.9);
}

.drawer-menu-link .q-item__section {
  padding: 0;
}

.drawer-menu-link__avatar {
  min-width: 26px;
  padding-right: 6px !important;
}

.drawer-menu-link__label {
  font-size: 13px;
  font-weight: 600;
}

.drawer-menu-link--active {
  background: #e9682f;
  color: #ffffff !important;
  box-shadow: inset 4px 0 0 #f6a204;
}

.drawer-footer {
  padding: 10px 8px 4px;
  margin-top: 8px;
  color: rgba(255, 255, 255, 0.6);
  font-size: 10px;
  line-height: 1.4;
}

.drawer-logout {
  min-height: 32px;
  margin: 4px 2px 0;
  border-radius: 8px;
  font-size: 13px;
  color: #ffffff;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.app-header {
  background: #ffffff;
  border-bottom: 3px solid #e9682f;
  color: #2b1a17;
}

.app-header .q-toolbar {
  min-height: 54px;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fdf0e7;
  border-radius: 99px;
  padding: 4px 12px 4px 5px;
}

.page-bg {
  background: #fff8f2;
}
</style>
