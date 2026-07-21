import axios from 'axios'

const client = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

async function ensureCsrfCookie() {
  await axios.get('/sanctum/csrf-cookie', { baseURL: '/', withCredentials: true })
}

client.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401)
      window.dispatchEvent(new CustomEvent('auth:unauthorized'))

    return Promise.reject(error)
  },
)

export { ensureCsrfCookie }
export default client
