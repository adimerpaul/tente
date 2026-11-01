export function companyData () {
  try {
    return JSON.parse(localStorage.getItem('empresaTente') || '{}')
  } catch {
    return {}
  }
}
