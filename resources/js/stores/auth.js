import { defineStore } from 'pinia'
import client, { ensureCsrfCookie } from '@/api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    ready: false,
  }),
  getters: {
    isAuthenticated: state => !!state.user,
    roles: state => state.user?.roles ?? [],
    primaryRole: state => state.user?.roles?.[0] ?? null,
    hasRole: state => role => (state.user?.roles ?? []).includes(role),
  },
  actions: {
    async login(email, password, remember = false) {
      await ensureCsrfCookie()
      const { data } = await client.post('/login', { email, password, remember })

      this.user = data.user

      return data.user
    },
    async logout() {
      await client.post('/logout')
      this.user = null
    },
    async fetchMe() {
      try {
        const { data } = await client.get('/me')

        this.user = data.user
      }
      catch {
        this.user = null
      }
      finally {
        this.ready = true
      }
    },
    homeRouteForRole() {
      return this.primaryRole ? '/dashboard' : '/login'
    },
  },
})
