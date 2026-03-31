import React from "react";
import { getAuthUser } from "../helpers/auth_storage";
import { APP_PERMISSIONS, hasPermission } from "../helpers/permissions";

const Navdata = () => {
  const authUser = getAuthUser();
  const menuItems: any = [
    {
      id: "dashboard",
      label: "Dashboard",
      icon: "ri-dashboard-line",
      link: "/dashboard",
      permission: APP_PERMISSIONS.dashboardView,
    },
    {
      id: "orders",
      label: "Orders",
      icon: "ri-shopping-cart-2-line",
      link: "/orders",
      permission: APP_PERMISSIONS.ordersView,
    },
    {
      id: "customers",
      label: "Customers",
      icon: "ri-group-line",
      link: "/customers",
      permission: APP_PERMISSIONS.customersView,
    },
    {
      id: "products",
      label: "Products",
      icon: "ri-box-3-line",
      link: "/products",
      permission: APP_PERMISSIONS.productsView,
    },
    {
      id: "salesChannels",
      label: "Sales Channels",
      icon: "ri-line-chart-line",
      link: "/sales-channels",
      permission: APP_PERMISSIONS.salesChannelsView,
    },
    {
      id: "websitePerformance",
      label: "Website Performance",
      icon: "ri-global-line",
      link: "/website-performance",
      permission: APP_PERMISSIONS.websitePerformanceView,
    },
    {
      label: "Sales",
      isHeader: true,
    },
    {
      id: "manualSales",
      label: "Manual Sales",
      icon: "ri-add-circle-line",
      link: "/manual-sales",
      permission: APP_PERMISSIONS.manualSalesView,
    },
    {
      label: "Administration",
      isHeader: true,
    },
    {
      id: "userManagement",
      label: "User Management",
      icon: "ri-user-settings-line",
      link: "/user-management",
      permission: APP_PERMISSIONS.usersManage,
    },
    {
      id: "rolesPermissions",
      label: "Roles & Permissions",
      icon: "ri-shield-user-line",
      link: "/roles-permissions",
      permission: APP_PERMISSIONS.rolesManage,
    },
    {
      id: "integrations",
      label: "Sync Data",
      icon: "ri-settings-3-line",
      link: "/integrations",
      permission: APP_PERMISSIONS.integrationsView,
    },
    {
      id: "auditLogs",
      label: "Audit Logs",
      icon: "ri-file-list-3-line",
      link: "/audit-logs",
      permission: APP_PERMISSIONS.auditLogsView,
    },
  ];

  const filteredMenuItems = menuItems.filter((item: any, index: number, items: any[]) => {
    if (!item.isHeader) {
      return hasPermission(authUser, item.permission);
    }

    const nextVisibleItem = items
      .slice(index + 1)
      .find((candidate) => !candidate.isHeader && hasPermission(authUser, candidate.permission));

    return Boolean(nextVisibleItem);
  });

  return <React.Fragment>{filteredMenuItems}</React.Fragment>;
};

export default Navdata;
