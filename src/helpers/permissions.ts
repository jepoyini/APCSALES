import { getAuthUser, setAuthUser } from "./auth_storage";

export const APP_PERMISSIONS = {
  dashboardView: "dashboard.view",
  profileView: "profile.view",
  customersView: "customers.view",
  ordersView: "orders.view",
  ordersExport: "orders.export",
  productsView: "products.view",
  salesChannelsView: "sales_channels.view",
  websitePerformanceView: "website_performance.view",
  manualSalesView: "manual_sales.view",
  manualSalesCreate: "manual_sales.create",
  manualSalesEdit: "manual_sales.edit",
  usersManage: "users.manage",
  rolesManage: "roles.manage",
  integrationsView: "integrations.view",
  integrationsSync: "integrations.sync",
  auditLogsView: "audit_logs.view",
} as const;

export type AppPermission = (typeof APP_PERMISSIONS)[keyof typeof APP_PERMISSIONS];
export type AppRole = "admin" | "manager" | "staff";
export type RolePermissionsMap = Record<AppRole, AppPermission[]>;

const ROLE_ALIASES: Record<string, AppRole> = {
  admin: "admin",
  administrator: "admin",
  superadmin: "admin",
  "super admin": "admin",
  manager: "manager",
  supervisor: "manager",
  lead: "manager",
  staff: "staff",
  user: "staff",
  employee: "staff",
};

const DEFAULT_ROLE_PERMISSIONS: RolePermissionsMap = {
  admin: Object.values(APP_PERMISSIONS),
  manager: [
    APP_PERMISSIONS.dashboardView,
    APP_PERMISSIONS.profileView,
    APP_PERMISSIONS.customersView,
    APP_PERMISSIONS.ordersView,
    APP_PERMISSIONS.ordersExport,
    APP_PERMISSIONS.productsView,
    APP_PERMISSIONS.salesChannelsView,
    APP_PERMISSIONS.websitePerformanceView,
    APP_PERMISSIONS.manualSalesView,
    APP_PERMISSIONS.manualSalesCreate,
    APP_PERMISSIONS.manualSalesEdit,
    APP_PERMISSIONS.usersManage,
    APP_PERMISSIONS.rolesManage,
    APP_PERMISSIONS.integrationsView,
    APP_PERMISSIONS.auditLogsView,
  ],
  staff: [
    APP_PERMISSIONS.dashboardView,
    APP_PERMISSIONS.profileView,
    APP_PERMISSIONS.customersView,
    APP_PERMISSIONS.ordersView,
    APP_PERMISSIONS.productsView,
    APP_PERMISSIONS.salesChannelsView,
    APP_PERMISSIONS.websitePerformanceView,
    APP_PERMISSIONS.manualSalesView,
  ],
};

const AVAILABLE_PERMISSIONS = Object.values(APP_PERMISSIONS);

let rolePermissionsCache: RolePermissionsMap = {
  admin: [...DEFAULT_ROLE_PERMISSIONS.admin],
  manager: [...DEFAULT_ROLE_PERMISSIONS.manager],
  staff: [...DEFAULT_ROLE_PERMISSIONS.staff],
};

const isAppPermission = (value: string): value is AppPermission =>
  AVAILABLE_PERMISSIONS.includes(value as AppPermission);

const sanitizePermissionList = (permissions: string[]): AppPermission[] =>
  Array.from(
    new Set(
      permissions
        .map((permission) => permission.trim())
        .filter((permission): permission is AppPermission => isAppPermission(permission))
    )
  );

const emitPermissionsUpdated = () => {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event("permissions-updated"));
  }
};

const cloneRolePermissionsMap = (value: RolePermissionsMap): RolePermissionsMap => ({
  admin: [...value.admin],
  manager: [...value.manager],
  staff: [...value.staff],
});

export const getAvailablePermissions = (): AppPermission[] => AVAILABLE_PERMISSIONS;

export const getDefaultRolePermissions = (): RolePermissionsMap =>
  cloneRolePermissionsMap(DEFAULT_ROLE_PERMISSIONS);

export const getRolePermissionsMap = (): RolePermissionsMap =>
  cloneRolePermissionsMap(rolePermissionsCache);

export const normalizeUserRole = (user?: any): AppRole => {
  const roleId = Number(user?.role_id ?? user?.roleId ?? user?.rank_id ?? user?.rankId);
  if (roleId === 1) return "admin";
  if (roleId === 2) return "manager";
  if (roleId === 3) return "staff";

  const roleValue = String(
    user?.role ?? user?.rank ?? user?.role_name ?? user?.roleName ?? user?.name ?? ""
  )
    .trim()
    .toLowerCase();

  return ROLE_ALIASES[roleValue] || "staff";
};

const syncAuthUserPermissions = (rolePermissions: RolePermissionsMap) => {
  const authUser = getAuthUser<any>();
  if (!authUser) return;

  const role = normalizeUserRole(authUser);
  setAuthUser({
    ...authUser,
    permissions: [...rolePermissions[role]],
  });
};

export const setRolePermissionsMap = (
  value: Partial<Record<AppRole, string[]>>
): RolePermissionsMap => {
  const next: RolePermissionsMap = {
    admin: sanitizePermissionList(value.admin ?? rolePermissionsCache.admin),
    manager: sanitizePermissionList(value.manager ?? rolePermissionsCache.manager),
    staff: sanitizePermissionList(value.staff ?? rolePermissionsCache.staff),
  };

  rolePermissionsCache = cloneRolePermissionsMap(next);
  syncAuthUserPermissions(rolePermissionsCache);
  emitPermissionsUpdated();
  return getRolePermissionsMap();
};

export const saveRolePermissionsMap = (
  value: Partial<Record<AppRole, string[]>>
): RolePermissionsMap => setRolePermissionsMap(value);

export const resetRolePermissionsMap = (): RolePermissionsMap =>
  setRolePermissionsMap(getDefaultRolePermissions());

const parsePermissionValue = (value: unknown): string[] => {
  if (!value) return [];

  if (Array.isArray(value)) {
    return value.flatMap((item) => parsePermissionValue(item)).filter(Boolean);
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if (!trimmed) return [];

    if (
      (trimmed.startsWith("[") && trimmed.endsWith("]")) ||
      (trimmed.startsWith("{") && trimmed.endsWith("}"))
    ) {
      try {
        return parsePermissionValue(JSON.parse(trimmed));
      } catch {
        return trimmed
          .split(/[,\n|]/)
          .map((item) => item.trim())
          .filter(Boolean);
      }
    }

    return trimmed
      .split(/[,\n|]/)
      .map((item) => item.trim())
      .filter(Boolean);
  }

  if (typeof value === "object") {
    const record = value as Record<string, unknown>;

    if (typeof record.name === "string") {
      return [record.name.trim()];
    }

    if (typeof record.permission === "string") {
      return [record.permission.trim()];
    }

    if (typeof record.code === "string") {
      return [record.code.trim()];
    }
  }

  return [];
};

export const hydrateRolePermissionsMap = (roles: any[]): RolePermissionsMap => {
  const defaults = getDefaultRolePermissions();
  const next: RolePermissionsMap = {
    admin: [...defaults.admin],
    manager: [...defaults.manager],
    staff: [...defaults.staff],
  };

  roles.forEach((roleRecord) => {
    const role = normalizeUserRole(roleRecord);
    next[role] = sanitizePermissionList(parsePermissionValue(roleRecord.permissions));
  });

  return setRolePermissionsMap(next);
};

export const ROLE_PERMISSIONS = getDefaultRolePermissions();

export const resolveUserPermissions = (user?: any): string[] => {
  if (!user) return [];

  const explicitPermissions = [
    ...parsePermissionValue(user.permissions),
    ...parsePermissionValue(user.permission),
    ...parsePermissionValue(user.modules),
    ...parsePermissionValue(user.access),
  ];

  const normalized = Array.from(
    new Set(explicitPermissions.map((permission) => permission.trim()).filter(Boolean))
  );

  if (normalized.length > 0) {
    return normalized;
  }

  return getRolePermissionsMap()[normalizeUserRole(user)];
};

export const hasPermission = (
  user: any,
  required?: string | string[],
  options?: { match?: "all" | "any" }
): boolean => {
  if (!required) return true;

  const requiredList = Array.isArray(required) ? required : [required];
  if (requiredList.length === 0) return true;

  const granted = new Set(resolveUserPermissions(user));
  if (granted.has("*")) return true;

  const match = options?.match || "all";
  return match === "any"
    ? requiredList.some((permission) => granted.has(permission))
    : requiredList.every((permission) => granted.has(permission));
};
