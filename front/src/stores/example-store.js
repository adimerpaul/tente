import { defineStore, acceptHMRUpdate } from 'pinia'

// Pedidos abiertos: se atienden varios clientes a la vez desde la misma caja.
// Viven en el store (no en la página) para no perderse al cambiar de pantalla.
export const CART_COUNT = 5

const emptyCart = () => ({ items: [], descuento: 0, observacion: '', cliente: null, cliente_nombre: '', tipo_consumo: 'LLEVAR' })

export const useCounterStore = defineStore('counter', {
  state: () => ({
    isLogged: false,
    user: {},
    permissions: [],
    carts: Array.from({ length: CART_COUNT }, emptyCart),
    activeCart: 0,
    // Se marca cuando una llamada al API falla sin respuesta del servidor.
    offline: false,
  }),

  getters: {
    hasPermission: (state) => (perm) => {
      if (Array.isArray(perm)) return perm.some(p => state.permissions.includes(p))
      return state.permissions.includes(perm)
    },
    currentCart: (state) => state.carts[state.activeCart] || state.carts[0],
    cartTotal: (state) => (index) => {
      const cart = state.carts[index]
      if (!cart) return 0
      const subtotal = cart.items.reduce((sum, i) => sum + Number(i.precio_venta || 0) * Number(i.cantidad || 0), 0)
      return Math.max(0, subtotal - Math.min(Math.max(Number(cart.descuento) || 0, 0), subtotal))
    },
    pendingCarts: (state) => state.carts.filter(c => c.items.length).length,
  },

  actions: {
    setActiveCart (index) {
      if (!Number.isInteger(index) || index < 0 || index >= CART_COUNT) return
      this.activeCart = index
    },
    resetCart (index = this.activeCart) {
      if (!this.carts[index]) return
      this.carts[index] = emptyCart()
    },
    logout () {
      this.isLogged = false
      this.user = {}
      this.permissions = []
      this.carts = Array.from({ length: CART_COUNT }, emptyCart)
      this.activeCart = 0
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useCounterStore, import.meta.hot))
}
