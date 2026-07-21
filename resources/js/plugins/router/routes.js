export const routes = [
  { path: '/', redirect: '/dashboard' },
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: 'dashboard', component: () => import('@/pages/dashboard.vue') },
      { path: 'customers', component: () => import('@/pages/customers.vue'), meta: { permission: 'customers.view' } },
      { path: 'products', component: () => import('@/pages/products.vue'), meta: { permission: 'products.view' } },
      { path: 'discounts', component: () => import('@/pages/discounts.vue'), meta: { permission: 'discounts.view' } },
      { path: 'transactions', component: () => import('@/pages/transactions.vue'), meta: { permission: 'transactions.view' } },
      { path: 'invoices', component: () => import('@/pages/invoices.vue'), meta: { permission: 'invoices.view' } },
      { path: 'invoices/:id', component: () => import('@/pages/invoice-detail.vue'), meta: { permission: 'invoices.view' } },
      { path: 'payments', component: () => import('@/pages/payments.vue'), meta: { permission: 'payments.view' } },
      { path: 'expenses', component: () => import('@/pages/expenses.vue'), meta: { roles: ['super-admin'] } },
      { path: 'users', component: () => import('@/pages/users.vue'), meta: { roles: ['super-admin'] } },
      { path: 'reports/transactions', component: () => import('@/pages/reports/transactions.vue'), meta: { roles: ['super-admin', 'reseller'] } },
      { path: 'reports/sales', component: () => import('@/pages/reports/sales.vue'), meta: { roles: ['super-admin', 'sales'] } },
      { path: 'reports/collector', component: () => import('@/pages/reports/collector.vue'), meta: { roles: ['super-admin', 'collector'] } },
      { path: 'reports/income-statement', component: () => import('@/pages/reports/income-statement.vue'), meta: { roles: ['super-admin'] } },
      { path: 'reports/balance-sheet', component: () => import('@/pages/reports/balance-sheet.vue'), meta: { roles: ['super-admin'] } },
      { path: 'account-settings', component: () => import('@/pages/account-settings.vue') },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      { path: 'login', component: () => import('@/pages/login.vue') },
      { path: '/:pathMatch(.*)*', component: () => import('@/pages/[...error].vue') },
    ],
  },
]
