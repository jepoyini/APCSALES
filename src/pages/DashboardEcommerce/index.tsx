import React, { useMemo, useState, useEffect } from "react";
import { motion } from "framer-motion";
import { DollarSign, ShoppingCart, TrendingUp, Users } from "lucide-react";
import {
  Badge,
  Card,
  CardBody,
  CardHeader,
  Col,
  Container,
  Input,
  Label,
  Row,
  Spinner,
} from "reactstrap";
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  format,
  isWithinInterval,
  endOfYear,
  startOfMonth,
  startOfQuarter,
  startOfYear,
  subYears,
  subDays,
} from "date-fns";

type KPI = {
  label: string;
  value: number;
  change: number;
  prefix?: string;
  icon: React.ComponentType<{ size?: number; className?: string }>;
};

type ProductRow = {
  id: string | number;
  product_name: string;
  sku: string;
  site: string;
  revenue: number;
  units_sold: number;
  auv: number;
};

type OrderRow = {
  id?: string | number;
  order_id?: string | number;
  site: string;
  status: string;
  total: number;
  revenue?: number;
  amount?: number;
  created_at?: string;
  order_date?: string;
  date_created?: string;
  channel?: string;
};

type CustomerRow = {
  id?: string | number;
  customer_id?: string | number;
  site?: string;
  created_at?: string;
  date_created?: string;
};

const chartColors = [
  "hsl(222,62%,22%)",
  "hsl(36,95%,52%)",
  "hsl(142,71%,45%)",
  "hsl(217,91%,60%)",
  "hsl(340,75%,55%)",
  "hsl(220,9%,46%)",
];

const SITE_COLORS: Record<string, string> = {
  APC: "primary",
  MP: "success",
  PNP: "warning",
};

const DATE_PRESETS = [
  { label: "Today", from: new Date(), to: new Date() },
  { label: "Yesterday", from: subDays(new Date(), 1), to: subDays(new Date(), 1) },
  { label: "Last 7 days", from: subDays(new Date(), 6), to: new Date() },
  { label: "Last 30 days", from: subDays(new Date(), 29), to: new Date() },
  {
    label: "Last Year",
    from: startOfYear(subYears(new Date(), 1)),
    to: endOfYear(subYears(new Date(), 1)),
  },
  { label: "Month to Date", from: startOfMonth(new Date()), to: new Date() },
  { label: "Quarter to Date", from: startOfQuarter(new Date()), to: new Date() },
  { label: "Year to Date", from: startOfYear(new Date()), to: new Date() },
];

const API_BASE = "https://apidb.americanplaquecompany.com/analytics";

const DashboardEcommerce = () => {
  document.title = "Dashboard | APC Sales Analytics";

  const [siteFilter, setSiteFilter] = useState<string>("all");
  const [datePreset, setDatePreset] = useState("Last 30 days");
  const [fromDate, setFromDate] = useState(format(subDays(new Date(), 29), "yyyy-MM-dd"));
  const [toDate, setToDate] = useState(format(new Date(), "yyyy-MM-dd"));

  const [products, setProducts] = useState<ProductRow[]>([]);
  const [orders, setOrders] = useState<OrderRow[]>([]);
  const [customers, setCustomers] = useState<CustomerRow[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(25);

  const toNumber = (val: any): number => {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
  };

  const normalizeSite = (val: any): string => {
    if (!val) return "Unknown";
    const s = String(val).trim().toLowerCase();
    if (s === "apc" || s.includes("american")) return "APC";
    if (s === "mp" || s.includes("master")) return "MP";
    if (s === "pnp" || s.includes("patch")) return "PNP";
    return String(val).toUpperCase();
  };

  const normalizeProducts = (raw: any): ProductRow[] => {
    const rows = Array.isArray(raw)
      ? raw
      : Array.isArray(raw?.products)
      ? raw.products
      : Array.isArray(raw?.data)
      ? raw.data
      : [];

    return rows.map((p: any, idx: number) => {
      const revenue = toNumber(p.revenue ?? p.total_revenue ?? p.sales ?? 0);
      const unitsSold = toNumber(p.units_sold ?? p.qty_sold ?? p.units ?? 0);

      // Product-level metric should be AUV, not AOV.
      // Prefer backend-provided AUV if present; otherwise compute from revenue / units_sold.
      const backendAuv = toNumber(p.auv ?? p.avg_unit_value ?? p.average_unit_value ?? 0);
      const computedAuv = unitsSold > 0 ? revenue / unitsSold : 0;

      return {
        id: p.id ?? p.product_id ?? idx,
        product_name: p.product_name ?? p.name ?? p.title ?? "Unnamed Product",
        sku: p.sku ?? p.product_sku ?? "",
        site: normalizeSite(p.site ?? p.website ?? p.source),
        revenue,
        units_sold: unitsSold,
        auv: backendAuv > 0 ? backendAuv : computedAuv,
      };
    });
  };

  const normalizeOrders = (raw: any): OrderRow[] => {
    const rows = Array.isArray(raw)
      ? raw
      : Array.isArray(raw?.orders)
      ? raw.orders
      : Array.isArray(raw?.data)
      ? raw.data
      : [];

    return rows.map((o: any, idx: number) => ({
      id: o.id ?? o.order_id ?? idx,
      order_id: o.order_id ?? o.id ?? idx,
      site: normalizeSite(o.site ?? o.website ?? o.source),
      status: String(o.status ?? o.order_status ?? "Unknown"),
      total: toNumber(o.total ?? o.order_total ?? o.grand_total ?? o.amount ?? o.revenue ?? 0),
      revenue: toNumber(o.revenue ?? o.total ?? o.order_total ?? o.amount ?? 0),
      amount: toNumber(o.amount ?? o.total ?? o.order_total ?? o.revenue ?? 0),
      created_at: o.created_at ?? o.order_date ?? o.date_created ?? o.date ?? null,
      order_date: o.order_date ?? o.created_at ?? o.date_created ?? o.date ?? null,
      date_created: o.date_created ?? o.created_at ?? o.order_date ?? o.date ?? null,
      channel: o.channel ?? o.order_type ?? "Online",
    }));
  };

  const normalizeCustomers = (raw: any): CustomerRow[] => {
    const rows = Array.isArray(raw)
      ? raw
      : Array.isArray(raw?.customers)
      ? raw.customers
      : Array.isArray(raw?.data)
      ? raw.data
      : [];

    return rows.map((c: any, idx: number) => ({
      id: c.id ?? c.customer_id ?? idx,
      customer_id: c.customer_id ?? c.id ?? idx,
      site: normalizeSite(c.site ?? c.website ?? c.source),
      created_at: c.created_at ?? c.date_created ?? c.date ?? null,
      date_created: c.date_created ?? c.created_at ?? c.date ?? null,
    }));
  };

  const getOrderDate = (o: OrderRow): Date | null => {
    const raw = o.created_at || o.order_date || o.date_created;
    if (!raw) return null;
    const d = new Date(raw);
    return Number.isNaN(d.getTime()) ? null : d;
  };

  const isDateInRange = (d: Date | null, start: Date, end: Date) => {
    if (!d) return false;
    return isWithinInterval(d, { start, end });
  };

  useEffect(() => {
    const loadData = async () => {
      setLoading(true);

      try {
        const [productsRes, ordersRes, customersRes] = await Promise.all([
          fetch(`${API_BASE}/products`),
          fetch(`${API_BASE}/orders`),
          fetch(`${API_BASE}/customers`),
        ]);

        const [productsJson, ordersJson, customersJson] = await Promise.all([
          productsRes.json(),
          ordersRes.json(),
          customersRes.json(),
        ]);

        setProducts(normalizeProducts(productsJson));
        setOrders(normalizeOrders(ordersJson));
        setCustomers(normalizeCustomers(customersJson));
      } catch (err) {
        console.error("Analytics fetch error:", err);
        setProducts([]);
        setOrders([]);
        setCustomers([]);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  useEffect(() => {
    setCurrentPage(1);
  }, [siteFilter, fromDate, toDate]);

  const filteredOrders = useMemo(() => {
    const start = new Date(fromDate);
    const end = new Date(toDate);

    return orders.filter((o) => {
      const siteOk =
        siteFilter === "all"
          ? true
          : normalizeSite(o.site).toLowerCase() === siteFilter;
      const dateOk = isDateInRange(getOrderDate(o), start, end);
      return siteOk && dateOk;
    });
  }, [orders, siteFilter, fromDate, toDate]);

  const filteredCustomers = useMemo(() => {
    const siteOk = (site?: string) =>
      siteFilter === "all"
        ? true
        : normalizeSite(site).toLowerCase() === siteFilter;

    return customers.filter((c) => siteOk(c.site));
  }, [customers, siteFilter]);

  const filteredTopProducts = useMemo(() => {
    const rows =
      siteFilter === "all"
        ? products
        : products.filter(
            (p) => normalizeSite(p.site).toLowerCase() === siteFilter
          );

    return [...rows].sort((a, b) => b.revenue - a.revenue);
  }, [siteFilter, products]);

  const totalProductPages = Math.max(
    1,
    Math.ceil(filteredTopProducts.length / pageSize)
  );

  const paginatedTopProducts = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    return filteredTopProducts.slice(start, end);
  }, [filteredTopProducts, currentPage, pageSize]);

  useEffect(() => {
    if (currentPage > totalProductPages) {
      setCurrentPage(totalProductPages);
    }
  }, [currentPage, totalProductPages]);

  const summary = useMemo(() => {
    const totalRevenue = filteredOrders.reduce(
      (sum, o) => sum + toNumber(o.revenue ?? o.total ?? o.amount),
      0
    );
    const totalOrders = filteredOrders.length;
    const avgOrderValue = totalOrders > 0 ? totalRevenue / totalOrders : 0;
    const customerCount = filteredCustomers.length;

    return {
      total_revenue: totalRevenue,
      total_orders: totalOrders,
      avg_order_value: avgOrderValue,
      customers: customerCount,
    };
  }, [filteredOrders, filteredCustomers]);

  const revenueTrend = useMemo(() => {
    const map = new Map<string, number>();

    filteredOrders.forEach((o) => {
      const d = getOrderDate(o);
      if (!d) return;
      const key = format(d, "yyyy-MM-dd");
      const val = toNumber(o.revenue ?? o.total ?? o.amount);
      map.set(key, (map.get(key) || 0) + val);
    });

    return Array.from(map.entries())
      .map(([date, revenue]) => ({ date, revenue }))
      .sort((a, b) => a.date.localeCompare(b.date));
  }, [filteredOrders]);

  const revenueBySite = useMemo(() => {
    const map = new Map<string, number>();

    filteredOrders.forEach((o) => {
      const site = normalizeSite(o.site);
      const val = toNumber(o.revenue ?? o.total ?? o.amount);
      map.set(site, (map.get(site) || 0) + val);
    });

    return Array.from(map.entries()).map(([site, revenue]) => ({ site, revenue }));
  }, [filteredOrders]);

  const orderStatus = useMemo(() => {
    const map = new Map<string, number>();

    filteredOrders.forEach((o) => {
      const status = o.status || "Unknown";
      map.set(status, (map.get(status) || 0) + 1);
    });

    return Array.from(map.entries()).map(([status, total]) => ({ status, total }));
  }, [filteredOrders]);

  const channelMix = useMemo(() => {
    const map = new Map<string, number>();
    let grandTotal = 0;

    filteredOrders.forEach((o) => {
      const channel = o.channel || "Online";
      const amount = toNumber(o.revenue ?? o.total ?? o.amount);
      map.set(channel, (map.get(channel) || 0) + amount);
      grandTotal += amount;
    });

    return Array.from(map.entries()).map(([channel, value]) => ({
      channel,
      value,
      percentage: grandTotal > 0 ? (value / grandTotal) * 100 : 0,
    }));
  }, [filteredOrders]);

  const kpiCards: KPI[] = [
    { label: "Total Revenue", value: summary.total_revenue, change: 0, prefix: "$", icon: DollarSign },
    { label: "Total Orders", value: summary.total_orders, change: 0, icon: ShoppingCart },
    { label: "Avg Order Value", value: summary.avg_order_value, change: 0, prefix: "$", icon: TrendingUp },
    { label: "Customers", value: summary.customers, change: 0, icon: Users },
  ];

  const handlePreset = (presetLabel: string) => {
    setDatePreset(presetLabel);
    const selected = DATE_PRESETS.find((p) => p.label === presetLabel);
    if (!selected) return;
    setFromDate(format(selected.from, "yyyy-MM-dd"));
    setToDate(format(selected.to, "yyyy-MM-dd"));
  };

  return (
    <div className="page-content">
      <Container fluid>
        <Row className="g-3 mb-4">
          <Col md={3}>
            <Label className="form-label">Site</Label>
            <Input
              type="select"
              value={siteFilter}
              onChange={(e) => setSiteFilter(e.target.value)}
            >
              <option value="all">All Sites</option>
              <option value="apc">APC</option>
              <option value="mp">MP</option>
              <option value="pnp">PNP</option>
            </Input>
          </Col>

          <Col md={3}>
            <Label className="form-label">Preset</Label>
            <Input
              type="select"
              value={datePreset}
              onChange={(e) => handlePreset(e.target.value)}
            >
              {DATE_PRESETS.map((p) => (
                <option key={p.label} value={p.label}>
                  {p.label}
                </option>
              ))}
            </Input>
          </Col>

          <Col md={3}>
            <Label className="form-label">From</Label>
            <Input
              type="date"
              value={fromDate}
              onChange={(e) => setFromDate(e.target.value)}
            />
          </Col>

          <Col md={3}>
            <Label className="form-label">To</Label>
            <Input
              type="date"
              value={toDate}
              onChange={(e) => setToDate(e.target.value)}
            />
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          {kpiCards.map((kpi) => (
            <Col key={kpi.label} xl={3} md={6}>
              <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
                <Card className="border-0 shadow-sm h-100">
                  <CardBody>
                    <div className="d-flex justify-content-between align-items-start mb-3">
                      <div
                        className="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center"
                        style={{ width: 38, height: 38 }}
                      >
                        <kpi.icon className="text-primary" size={16} />
                      </div>
                    </div>

                    <h3 className="mb-1">
                      {loading ? (
                        <Spinner size="sm" color="primary" />
                      ) : (
                        <>
                          {kpi.prefix}
                          {Number(kpi.value || 0).toLocaleString(undefined, {
                            minimumFractionDigits: kpi.prefix ? 2 : 0,
                            maximumFractionDigits: kpi.prefix ? 2 : 0,
                          })}
                        </>
                      )}
                    </h3>

                    <p className="text-muted mb-0">{kpi.label}</p>
                  </CardBody>
                </Card>
              </motion.div>
            </Col>
          ))}
        </Row>

        <Row className="g-3 mb-4">
          <Col xl={8}>
            <Card className="border-0 shadow-sm h-100">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-1">Revenue Trend</h6>
              </CardHeader>

              <CardBody style={{ height: 330 }}>
                {loading ? (
                  <div className="h-100 d-flex align-items-center justify-content-center">
                    <div className="d-inline-flex align-items-center gap-2 text-muted">
                      <Spinner size="sm" color="primary" />
                      <span>Loading dashboard...</span>
                    </div>
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={revenueTrend}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                      <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                      <YAxis
                        tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
                        tick={{ fontSize: 11 }}
                      />
                      <Tooltip formatter={(v: number) => `$${v.toLocaleString()}`} />
                      <Area
                        type="monotone"
                        dataKey="revenue"
                        stroke="hsl(222,62%,22%)"
                        fill="hsl(222,62%,22%,0.2)"
                        strokeWidth={2}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                )}
              </CardBody>
            </Card>
          </Col>

          <Col xl={4}>
            <Card className="border-0 shadow-sm h-100">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0">Channel Mix</h6>
              </CardHeader>

              <CardBody>
                <div style={{ height: 200 }}>
                  {loading ? (
                    <div className="h-100 d-flex align-items-center justify-content-center">
                      <div className="d-inline-flex align-items-center gap-2 text-muted">
                        <Spinner size="sm" color="primary" />
                        <span>Loading dashboard...</span>
                      </div>
                    </div>
                  ) : (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={channelMix}
                          dataKey="value"
                          nameKey="channel"
                          innerRadius={50}
                          outerRadius={75}
                        >
                          {channelMix.map((_, i) => (
                            <Cell key={i} fill={chartColors[i % chartColors.length]} />
                          ))}
                        </Pie>
                        <Tooltip formatter={(v: number) => `$${v.toLocaleString()}`} />
                        <Legend iconSize={8} wrapperStyle={{ fontSize: 11 }} />
                      </PieChart>
                    </ResponsiveContainer>
                  )}
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          <Col xl={6}>
            <Card className="border-0 shadow-sm h-100">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0">Revenue by Website</h6>
              </CardHeader>

              <CardBody style={{ height: 300 }}>
                {loading ? (
                  <div className="h-100 d-flex align-items-center justify-content-center">
                    <div className="d-inline-flex align-items-center gap-2 text-muted">
                      <Spinner size="sm" color="primary" />
                      <span>Loading dashboard...</span>
                    </div>
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={revenueBySite} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                      <XAxis
                        type="number"
                        tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
                      />
                      <YAxis type="category" dataKey="site" width={140} />
                      <Tooltip formatter={(v: number) => `$${v.toLocaleString()}`} />
                      <Bar dataKey="revenue" radius={[0, 4, 4, 0]}>
                        {revenueBySite.map((_, i) => (
                          <Cell key={i} fill={chartColors[i % chartColors.length]} />
                        ))}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                )}
              </CardBody>
            </Card>
          </Col>

          <Col xl={6}>
            <Card className="border-0 shadow-sm h-100">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0">Order Status Breakdown</h6>
              </CardHeader>

              <CardBody style={{ height: 300 }}>
                {loading ? (
                  <div className="h-100 d-flex align-items-center justify-content-center">
                    <div className="d-inline-flex align-items-center gap-2 text-muted">
                      <Spinner size="sm" color="primary" />
                      <span>Loading dashboard...</span>
                    </div>
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie data={orderStatus} dataKey="total" nameKey="status">
                        {orderStatus.map((_, i) => (
                          <Cell key={i} fill={chartColors[i % chartColors.length]} />
                        ))}
                      </Pie>
                      <Tooltip />
                      <Legend iconSize={8} wrapperStyle={{ fontSize: 11 }} />
                    </PieChart>
                  </ResponsiveContainer>
                )}
              </CardBody>
            </Card>
          </Col>
        </Row>

        <Row>
          <Col>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0 d-flex justify-content-between align-items-center">
                <h6 className="mb-0">Top Products by Revenue</h6>
                {loading && (
                  <div className="d-inline-flex align-items-center gap-2 text-muted small">
                    <Spinner size="sm" color="primary" />
                    <span>Loading dashboard...</span>
                  </div>
                )}
              </CardHeader>

              <CardBody>
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Site</th>
                        <th className="text-end">Revenue</th>
                        <th className="text-end">Units</th>
                        <th className="text-end">AUV</th>
                      </tr>
                    </thead>

                    <tbody>
                      {!loading &&
                        paginatedTopProducts.map((p) => (
                          <tr key={p.id}>
                            <td className="fw-medium">{p.product_name}</td>
                            <td>
                              <code>{p.sku}</code>
                            </td>
                            <td>
                              <Badge color={SITE_COLORS[p.site] || "secondary"}>
                                {p.site}
                              </Badge>
                            </td>
                            <td className="text-end">
                              ${p.revenue.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                              })}
                            </td>
                            <td className="text-end">
                              {p.units_sold.toLocaleString()}
                            </td>
                            <td className="text-end">
                              ${p.auv.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                              })}
                            </td>
                          </tr>
                        ))}

                      {loading && (
                        <tr>
                          <td colSpan={6} className="text-center py-4">
                            <div className="d-inline-flex align-items-center gap-2 text-muted">
                              <Spinner size="sm" color="primary" />
                              <span>Loading dashboard...</span>
                            </div>
                          </td>
                        </tr>
                      )}

                      {!loading && paginatedTopProducts.length === 0 && (
                        <tr>
                          <td colSpan={6} className="text-center text-muted py-4">
                            No product data found for the selected filters.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>

                <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                  <div className="d-flex align-items-center gap-2">
                    <span className="text-muted small">Rows per page</span>
                    <Input
                      type="select"
                      value={pageSize}
                      onChange={(e) => {
                        setPageSize(Number(e.target.value));
                        setCurrentPage(1);
                      }}
                      style={{ width: 90 }}
                    >
                      <option value={10}>10</option>
                      <option value={25}>25</option>
                      <option value={50}>50</option>
                      <option value={100}>100</option>
                    </Input>
                  </div>

                  <div className="text-muted small">
                    Showing{" "}
                    {filteredTopProducts.length === 0
                      ? 0
                      : (currentPage - 1) * pageSize + 1}
                    {" - "}
                    {Math.min(currentPage * pageSize, filteredTopProducts.length)}
                    {" of "}
                    {filteredTopProducts.length}
                  </div>

                  <div className="d-flex align-items-center gap-2">
                    <button
                      type="button"
                      className="btn btn-sm btn-light"
                      disabled={currentPage === 1}
                      onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                    >
                      Prev
                    </button>

                    <span className="small">
                      Page {currentPage} of {totalProductPages}
                    </span>

                    <button
                      type="button"
                      className="btn btn-sm btn-light"
                      disabled={currentPage === totalProductPages}
                      onClick={() =>
                        setCurrentPage((p) => Math.min(totalProductPages, p + 1))
                      }
                    >
                      Next
                    </button>
                  </div>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default DashboardEcommerce;