import React from "react";
import { Navigate } from "react-router-dom";

//Dashboard
import DashboardEcommerce from "../pages/DashboardEcommerce";

import Basic404 from '../pages/AuthenticationInner/Errors/Basic404';
import Cover404 from '../pages/AuthenticationInner/Errors/Cover404';
import Alt404 from '../pages/AuthenticationInner/Errors/Alt404';
import Error500 from '../pages/AuthenticationInner/Errors/Error500';
import Offlinepage from "../pages/AuthenticationInner/Errors/Offlinepage";

// //login
import Login from "../pages/Authentication/Login";
import ForgetPasswordPage from "../pages/Authentication/ForgetPassword";
import Logout from "../pages/Authentication/Logout";
import Register from "../pages/Authentication/Register";

// User Profile
import UserProfile from "../pages/Profile/User-profile";
import CustomersPage from "../pages/Customers";
import OrdersPage from "../pages/Orders";
import ProductsPage from "../pages/Products";
import SalesChannelsPage from "../pages/SalesChannels";
import WebsitePerformancePage from "../pages/WebsitePerformance";
import ManualSalesPage from "../pages/ManualSales";
import UserManagementPage from "../pages/UserManagement";
import RolesPermissionsPage from "../pages/RolesPermissions";
import SyncDataPage from "../pages/SynchData";
import AuditLogsPage from "../pages/AuditLogs";
import { APP_PERMISSIONS } from "../helpers/permissions";


const authProtectedRoutes = [
  { path: "/dashboard", component: <DashboardEcommerce />, permission: APP_PERMISSIONS.dashboardView },
  { path: "/index", component: <DashboardEcommerce />, permission: APP_PERMISSIONS.dashboardView },
 

  //User Profile
  { path: "/profile", component: <UserProfile />, permission: APP_PERMISSIONS.profileView },
  { path: "/customers", component: <CustomersPage />, permission: APP_PERMISSIONS.customersView },
  { path: "/orders", component: <OrdersPage />, permission: APP_PERMISSIONS.ordersView },
  { path: "/products", component: <ProductsPage />, permission: APP_PERMISSIONS.productsView },
  { path: "/sales-channels", component: <SalesChannelsPage />, permission: APP_PERMISSIONS.salesChannelsView },
  { path: "/website-performance", component: <WebsitePerformancePage />, permission: APP_PERMISSIONS.websitePerformanceView },
  { path: "/manual-sales", component: <ManualSalesPage />, permission: APP_PERMISSIONS.manualSalesView },
  { path: "/user-management", component: <UserManagementPage />, permission: APP_PERMISSIONS.usersManage },
  { path: "/roles-permissions", component: <RolesPermissionsPage />, permission: APP_PERMISSIONS.rolesManage },
  { path: "/integrations", component: <SyncDataPage />, permission: APP_PERMISSIONS.integrationsView },
  { path: "/audit-logs", component: <AuditLogsPage />, permission: APP_PERMISSIONS.auditLogsView },

  // this route should be at the end of all other routes
  // eslint-disable-next-line react/display-name
  {
    path: "/",
    exact: true,
    component: <Navigate to="/dashboard" />,
  },
  { path: "*", component: <Navigate to="/dashboard" />, permission: APP_PERMISSIONS.dashboardView },
];

const publicRoutes = [
  // Authentication Page
  { path: "/logout", component: <Logout /> },
  { path: "/login", component: <Login /> },
  { path: "/forgot-password", component: <ForgetPasswordPage /> },
  { path: "/register", component: <Register /> },

  { path: "/auth-404-basic", component: <Basic404 /> },
  { path: "/auth-404-cover", component: <Cover404 /> },
  { path: "/auth-404-alt", component: <Alt404 /> },
  { path: "/auth-500", component: <Error500 /> },
  { path: "/auth-offline", component: <Offlinepage /> },

];

export { authProtectedRoutes, publicRoutes };
