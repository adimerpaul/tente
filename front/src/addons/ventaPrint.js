import { Printd } from 'printd'
import { companyData } from './empresa'

const css = `
@page{size:80mm auto;margin:4mm}body{margin:0}.ticket{font-family:Arial,sans-serif;font-size:11px;color:#111}
h2{text-align:center;margin:0}.center{text-align:center}.line{border-top:1px dashed #333;margin:7px 0}
.logo{display:block;width:150px;max-height:80px;object-fit:contain;margin:0 auto 4px}
table{width:100%;border-collapse:collapse}th,td{padding:2px;vertical-align:top}.right{text-align:right}.bold{font-weight:bold}
.total{font-size:15px}.cancelled{font-size:28px;text-align:center;font-weight:bold}
.pedido{font-size:30px;font-weight:bold;text-align:center;margin:4px 0}.nota{font-size:11px;font-style:italic}
.cocina td{font-size:15px;font-weight:bold}.cocina .nota{font-size:13px;font-weight:normal}
`
const esc = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
const money = value => Number(value || 0).toFixed(2)
const consumo = sale => sale.tipo_consumo === 'LLEVAR' ? 'PARA LLEVAR' : 'EN MESA'

/** Ticket del cliente: con precios, pagos y número de pedido. */
export function printSale (sale) {
  const company = companyData()
  const logoUrl = company.logo_url || `${window.location.origin}/logo-tente.png`
  const rows = (sale.detalles || []).map(item => `<tr><td>${item.cantidad} × ${esc(item.nombre)}${item.observacion ? `<div class="nota">${esc(item.observacion)}</div>` : ''}<small>Bs ${money(item.precio_venta)} c/u</small></td><td class="right">${money(item.total)}</td></tr>`).join('')
  const element = document.createElement('div')
  element.innerHTML = `<div class="ticket"><img class="logo" src="${logoUrl}" alt="Tenté"><div class="center"><b>${esc(company.nombre_empresa || 'Tenté')}</b>${company.eslogan ? `<br>${esc(company.eslogan)}` : ''}<br>${esc(company.direccion || '')}${company.telefono ? `<br>Tel: ${esc(company.telefono)}` : ''}${company.nit ? ` · NIT: ${esc(company.nit)}` : ''}</div><div class="line"></div>
  <div class="center">PEDIDO</div><div class="pedido">#${esc(sale.ticket)}</div><div class="center"><b>${consumo(sale)}</b></div><div class="line"></div>
  <div>${esc(sale.numero)}<br>Fecha: ${new Date(sale.fecha).toLocaleString('es-BO')}<br>Cliente: ${esc(sale.cliente_nombre)}${sale.cliente_documento ? ` (${esc(sale.cliente_documento)})` : ''}<br>Atendió: ${esc(sale.usuario_nombre)}<br>Pago: ${esc(sale.tipo_pago)}</div>
  <div class="line"></div><table><tbody>${rows}</tbody></table><div class="line"></div>
  <table><tr><td>Subtotal</td><td class="right">${money(sale.subtotal)}</td></tr>${Number(sale.descuento) ? `<tr><td>Descuento</td><td class="right">-${money(sale.descuento)}</td></tr>` : ''}
  ${sale.tipo_pago === 'COMBINADO' ? `<tr><td>Efectivo</td><td class="right">${money(sale.monto_efectivo)}</td></tr><tr><td>QR</td><td class="right">${money(sale.monto_qr)}</td></tr>` : ''}
  <tr class="bold total"><td>TOTAL Bs</td><td class="right">${money(sale.total)}</td></tr>${sale.recibido ? `<tr><td>Recibido</td><td class="right">${money(sale.recibido)}</td></tr><tr class="bold"><td>Cambio</td><td class="right">${money(sale.recibido - sale.total)}</td></tr>` : ''}</table>
  ${sale.estado === 'ANULADA' ? '<div class="cancelled">ANULADA</div>' : ''}<div class="line"></div><div class="center">¡Gracias por su preferencia!</div></div>`
  new Printd().print(element, [css])
}

/** Comanda para cocina: sin precios, letra grande, con las notas de cada plato. */
export function printKitchen (sale) {
  const rows = (sale.detalles || []).map(item => `<tr><td>${item.cantidad}</td><td>${esc(item.nombre)}${item.observacion ? `<div class="nota">» ${esc(item.observacion)}</div>` : ''}</td></tr>`).join('')
  const element = document.createElement('div')
  element.innerHTML = `<div class="ticket"><div class="center"><b>COMANDA DE COCINA</b></div><div class="pedido">#${esc(sale.ticket)}</div><div class="center"><b>${consumo(sale)}</b><br>${esc(sale.cliente_nombre)}<br>${new Date(sale.fecha).toLocaleTimeString('es-BO')}</div><div class="line"></div>
  <table class="cocina"><tbody>${rows}</tbody></table>${sale.observacion ? `<div class="line"></div><div class="nota">${esc(sale.observacion)}</div>` : ''}<div class="line"></div></div>`
  new Printd().print(element, [css])
}
