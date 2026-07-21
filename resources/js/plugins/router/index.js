import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { routes } from './routes'

// NB: import.meta.env.BASE_URL is deliberately NOT used here — laravel-vite-plugin
// sets Vite's build `base` to `/build/` for asset resolution, which would otherwise
// leak into the SPA history base and prefix every route with `/build`.
const router = createRouter({
  history: createWebHistory('/'),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (!authStore.ready)
    await authStore.fetchMe()

  if (to.path === '/login') {
    if (authStore.isAuthenticated)
      return next(authStore.homeRouteForRole())

    return next()
  }

  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  if (requiresAuth && !authStore.isAuthenticated)
    return next('/login')

  const allowedRoles = to.meta.roles
  if (allowedRoles && !allowedRoles.some(role => authStore.hasRole(role)))
    return next('/dashboard')

  const requiredPermission = to.meta.permission
  if (requiredPermission && !authStore.hasRole('super-admin') && !(authStore.user?.permissions ?? []).includes(requiredPermission))
    return next('/dashboard')

  next()
})

export default function (app) {
  app.use(router)
}
export { router }
