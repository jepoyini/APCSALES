import React, { useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  Search,
  ShoppingCart,
  DollarSign,
  TrendingUp,
} from "lucide-react";
import {
  Badge,
  Button,
  Card,
  CardBody,
  Col,
  Container,
  Input,
  InputGroup,
  InputGroupText,
  Label,
  Row,
  Spinner,
} from "reactstrap";
import { format, startOfMonth, startOfQuarter, startOfYear, subDays } from "date-fns";
import BreadCrumb from "../../Components/Common/BreadCrumb";
import { usePermissions } from "../../Components/Hooks/UserHooks";
import { APP_PERMISSIONS } from "../../helpers/permissions";

type SiteId = "APC" | "MP" | "PNP";
type Channel = "online" | "walkin" | "callin" | string;
type OrderStatus =
  | "Completed"
  | "Processing"
  | "Pending"
  | "Refunded"
  | "Cancelled"
  | "On-Hold"
  | string;

type Order = {
  id: string;
  order_number: string;
  site_id: SiteId;
  channel: Channel;
  customer_name: string;
  customer_email: string;
  total: number;
  status: OrderStatus;
  items_count?: number;
  payment_method: string;
  created_at: string;
};

const SITES: Array<{ id: SiteId; name: string }> = [
  { id: "APC", name: "American Plaque Co." },
  { id: "MP", name: "Master Plaques" },
  { id: "PNP", name: "Plaques & Patches" },
];

const STATUS_OPTIONS = [
  "Completed",
  "Processing",
  "Pending",
  "Refunded",
  "Cancelled",
  "On-Hold",
];

const STATUS_COLORS: Record<string, string> = {
  Completed: "success",
  Processing: "info",
  Pending: "warning",
  Refunded: "danger",
  Cancelled: "secondary",
  "On-Hold": "secondary",
};

const SITE_COLORS: Record<SiteId, string> = {
  APC: "primary",
  MP: "success",
  PNP: "warning",
};

const DATE_PRESETS = [
  {
    label: "Today",
    from: format(new Date(), "yyyy-MM-dd"),
    to: format(new Date(), "yyyy-MM-dd"),
  },
  {
    label: "Yesterday",
    from: format(subDays(new Date(), 1), "yyyy-MM-dd"),
    to: format(subDays(new Date(), 1), "yyyy-MM-dd"),
  },
  {
    label: "Last 7 Days",
    from: format(subDays(new Date(), 6), "yyyy-MM-dd"),
    to: format(new Date(), "yyyy-MM-dd"),
  },
  {
    label: "Last 30 Days",
    from: format(subDays(new Date(), 29), "yyyy-MM-dd"),
    to: format(new Date(), "yyyy-MM-dd"),
  },
  {
    label: "MTD",
    from: format(startOfMonth(new Date()), "yyyy-MM-dd"),
    to: format(new Date(), "yyyy-MM-dd"),
  },
  {
    label: "QTD",
    from: format(startOfQuarter(new Date()), "yyyy-MM-dd"),
    to: format(new Date(), "yyyy-MM-dd"),
  },
  {
    label: "YTD",
    from: format(startOfYear(new Date()), "yyyy-MM-dd"),
    to: format(new Date(), "yyyy-MM-dd"),
  },
];

const OrdersPage: React.FC = () => {
  document.title = "Orders | APC Sales Analytics";
  const { hasPermission } = usePermissions();

  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);

  const [search, setSearch] = useState("");
  const [siteFilter, setSiteFilter] = useState<string>("all");
  const [statusFilter, setStatusFilter] = useState<string>("all");

  const [datePreset, setDatePreset] = useState("Last 30 Days");
  const [fromDate, setFromDate] = useState(format(subDays(new Date(), 29), "yyyy-MM-dd"));
  const [toDate, setToDate] = useState(format(new Date(), "yyyy-MM-dd"));

  const [page, setPage] = useState(1);
  const perPage = 10;

  const normalizeSite = (site: any): SiteId => {
    const s = String(site || "").trim().toUpperCase();
    if (s === "APC") return "APC";
    if (s === "MP") return "MP";
    if (s === "PNP") return "PNP";
    return "APC";
  };

  const normalizeStatus = (status: any): string => {
    const s = String(status || "").trim();
    if (!s) return "Pending";

    const lower = s.toLowerCase();
    if (lower === "completed") return "Completed";
    if (lower === "processing") return "Processing";
    if (lower === "pending") return "Pending";
    if (lower === "refunded") return "Refunded";
    if (lower === "cancelled" || lower === "canceled") return "Cancelled";
    if (lower === "on-hold" || lower === "on hold") return "On-Hold";

    return s;
  };

  const applyPreset = (presetLabel: string) => {
    setDatePreset(presetLabel);
    const preset = DATE_PRESETS.find((p) => p.label === presetLabel);
    if (!preset) return;
    setFromDate(preset.from);
    setToDate(preset.to);
  };

  useEffect(() => {
    const fetchOrders = async () => {
      try {
        setLoading(true);

        const res = await fetch("https://apidb.americanplaquecompany.com/analytics/orders");
        const data = await res.json();

        if (data.status === "success") {
          const rows = Array.isArray(data.orders)
            ? data.orders
            : Array.isArray(data.data)
            ? data.data
            : [];

          const mapped: Order[] = rows.map((o: any, index: number) => ({
            id: String(o.id ?? o.order_id ?? index),
            order_number: String(o.order_number ?? o.id ?? ""),
            site_id: normalizeSite(o.site ?? o.site_id),
            channel: String(o.channel ?? "online"),
            customer_name: String(o.customer_name ?? "Unknown Customer"),
            customer_email: String(o.customer_email ?? ""),
            total: Number(o.total ?? o.order_total ?? o.amount ?? 0),
            status: normalizeStatus(o.status),
            payment_method: String(o.payment_method ?? ""),
            created_at: String(o.order_date ?? o.created_at ?? o.date_created ?? ""),
          }));

          setOrders(mapped);
        } else {
          setOrders([]);
        }
      } catch (err) {
        console.error("Error fetching orders:", err);
        setOrders([]);
      } finally {
        setLoading(false);
      }
    };

    fetchOrders();
  }, []);

  const filtered = useMemo(() => {
    return orders.filter((o) => {
      const q = search.trim().toLowerCase();

      if (
        q &&
        !String(o.customer_name).toLowerCase().includes(q) &&
        !String(o.order_number).toLowerCase().includes(q) &&
        !String(o.customer_email).toLowerCase().includes(q)
      ) {
        return false;
      }

      if (siteFilter !== "all" && o.site_id !== siteFilter) {
        return false;
      }

      if (statusFilter !== "all" && o.status !== statusFilter) {
        return false;
      }

      if (fromDate) {
        const orderDate = new Date(o.created_at);
        const from = new Date(fromDate);
        from.setHours(0, 0, 0, 0);
        if (orderDate < from) return false;
      }

      if (toDate) {
        const orderDate = new Date(o.created_at);
        const to = new Date(toDate);
        to.setHours(23, 59, 59, 999);
        if (orderDate > to) return false;
      }

      return true;
    });
  }, [orders, search, siteFilter, statusFilter, fromDate, toDate]);

  useEffect(() => {
    setPage(1);
  }, [search, siteFilter, statusFilter, fromDate, toDate, datePreset]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
  const paged = filtered.slice((page - 1) * perPage, page * perPage);

  const kpis = useMemo(() => {
    const ordersCount = filtered.length;
    const totalRevenue = filtered.reduce((sum, order) => sum + Number(order.total || 0), 0);
    const avgOrderValue = ordersCount > 0 ? totalRevenue / ordersCount : 0;

    return {
      ordersCount,
      totalRevenue,
      avgOrderValue,
    };
  }, [filtered]);

  const exportCsv = () => {
    if (!hasPermission(APP_PERMISSIONS.ordersExport)) return;

    const headers = [
      "Order",
      "Customer",
      "Email",
      "Site",
      "Channel",
      "Total",
      "Status",
      "Payment",
      "Date",
    ];

    const rows = filtered.map((o) => [
      o.order_number,
      o.customer_name,
      o.customer_email,
      o.site_id,
      o.channel,
      o.total.toFixed(2),
      o.status,
      o.payment_method,
      new Date(o.created_at).toLocaleDateString(),
    ]);

    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "orders.csv");
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Orders" pageTitle="Dashboard" />

        <Row className="mb-4 align-items-center">
	          <Col md={8}>
	            <h2 className="mb-1">Orders</h2>
	            {loading ? (
	              <p className="text-muted mb-0 d-inline-flex align-items-center gap-2">
                  <Spinner size="sm" color="primary" />
                  <span>Loading orders...</span>
                </p>
	            ) : (
	              <p className="text-muted mb-0">{filtered.length} orders found</p>
	            )}
	          </Col>

          <Col md={4} className="text-md-end mt-3 mt-md-0">
            <Button
              color="light"
              className="border d-inline-flex align-items-center gap-2"
              onClick={exportCsv}
              disabled={!hasPermission(APP_PERMISSIONS.ordersExport)}
            >
              <Download size={14} /> Export CSV
            </Button>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          <Col xl={4} md={6}>
            <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <Card className="border-0 shadow-sm h-100">
                <CardBody>
                  <div className="d-flex justify-content-between align-items-start mb-3">
                    <div
                      className="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center"
                      style={{ width: 38, height: 38 }}
                    >
                      <ShoppingCart className="text-primary" size={16} />
                    </div>
	                  </div>
	                  <h3 className="mb-1">
                      {loading ? (
                        <Spinner size="sm" color="primary" />
                      ) : (
                        kpis.ordersCount.toLocaleString()
                      )}
                    </h3>
	                  <p className="text-muted mb-0">Orders Count</p>
	                </CardBody>
              </Card>
            </motion.div>
          </Col>

          <Col xl={4} md={6}>
            <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <Card className="border-0 shadow-sm h-100">
                <CardBody>
                  <div className="d-flex justify-content-between align-items-start mb-3">
                    <div
                      className="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center"
                      style={{ width: 38, height: 38 }}
                    >
                      <DollarSign className="text-success" size={16} />
                    </div>
                  </div>
	                  <h3 className="mb-1">
                      {loading ? (
                        <Spinner size="sm" color="primary" />
                      ) : (
                        <>
                          $
                          {kpis.totalRevenue.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          })}
                        </>
                      )}
                    </h3>
	                  <p className="text-muted mb-0">Total Revenue</p>
	                </CardBody>
              </Card>
            </motion.div>
          </Col>

          <Col xl={4} md={6}>
            <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <Card className="border-0 shadow-sm h-100">
                <CardBody>
                  <div className="d-flex justify-content-between align-items-start mb-3">
                    <div
                      className="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center"
                      style={{ width: 38, height: 38 }}
                    >
                      <TrendingUp className="text-warning" size={16} />
                    </div>
                  </div>
	                  <h3 className="mb-1">
                      {loading ? (
                        <Spinner size="sm" color="primary" />
                      ) : (
                        <>
                          $
                          {kpis.avgOrderValue.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          })}
                        </>
                      )}
                    </h3>
	                  <p className="text-muted mb-0">Avg Order Value</p>
	                </CardBody>
              </Card>
            </motion.div>
          </Col>
        </Row>

        <Card className="border-0 shadow-sm">
          <CardBody>
            <Row className="g-3 mb-4">
              <Col lg={3}>
                <Label className="form-label">Search</Label>
                <InputGroup>
                  <InputGroupText>
                    <Search size={16} />
                  </InputGroupText>
                  <Input
                    placeholder="Search orders..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                  />
                </InputGroup>
              </Col>

              <Col lg={2}>
                <Label className="form-label">Preset</Label>
                <Input
                  type="select"
                  value={datePreset}
                  onChange={(e) => applyPreset(e.target.value)}
                >
                  {DATE_PRESETS.map((preset) => (
                    <option key={preset.label} value={preset.label}>
                      {preset.label}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col lg={2}>
                <Label className="form-label">From</Label>
                <Input
                  type="date"
                  value={fromDate}
                  onChange={(e) => {
                    setFromDate(e.target.value);
                    setDatePreset("Custom");
                  }}
                />
              </Col>

              <Col lg={2}>
                <Label className="form-label">To</Label>
                <Input
                  type="date"
                  value={toDate}
                  onChange={(e) => {
                    setToDate(e.target.value);
                    setDatePreset("Custom");
                  }}
                />
              </Col>

              <Col lg={1}>
                <Label className="form-label">Sites</Label>
                <Input
                  type="select"
                  value={siteFilter}
                  onChange={(e) => setSiteFilter(e.target.value)}
                >
                  <option value="all">All</option>
                  {SITES.map((site) => (
                    <option key={site.id} value={site.id}>
                      {site.id}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col lg={2}>
                <Label className="form-label">Status</Label>
                <Input
                  type="select"
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                >
                  <option value="all">All Statuses</option>
                  {STATUS_OPTIONS.map((status) => (
                    <option key={status} value={status}>
                      {status}
                    </option>
                  ))}
                </Input>
              </Col>
            </Row>

            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Site</th>
                    <th>Channel</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                  </tr>
                </thead>

                <tbody>
                  {!loading &&
                    paged.map((order, i) => (
                      <motion.tr
                        key={order.id}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: i * 0.03 }}
                      >
                        <td className="fw-semibold">{order.order_number}</td>

                        <td>
                          <div className="fw-medium">{order.customer_name}</div>
                          <small className="text-muted">{order.customer_email}</small>
                        </td>

                        <td>
                          <Badge color={SITE_COLORS[order.site_id]} className="text-uppercase">
                            {order.site_id}
                          </Badge>
                        </td>

                        <td className="text-capitalize">{order.channel}</td>

                        <td>
                          $
                          {Number(order.total).toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          })}
                        </td>

                        <td>
                          <Badge
                            color={STATUS_COLORS[order.status] || "secondary"}
                            className="text-capitalize"
                          >
                            {order.status}
                          </Badge>
                        </td>

                        <td>{order.payment_method}</td>
                        <td>{new Date(order.created_at).toLocaleDateString()}</td>
                      </motion.tr>
                    ))}

                  {!loading && paged.length === 0 && (
                    <tr>
                      <td colSpan={8} className="text-center text-muted py-4">
                        No orders found for the selected filters.
                      </td>
                    </tr>
                  )}

	                  {loading && (
	                    <tr>
	                      <td colSpan={8} className="text-center py-4">
	                        <div className="d-inline-flex align-items-center gap-2 text-muted">
                            <Spinner size="sm" color="primary" />
                            <span>Loading orders...</span>
                          </div>
	                      </td>
	                    </tr>
	                  )}
                </tbody>
              </table>
            </div>

            <div className="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
              <small className="text-muted">
                Page {page} of {totalPages}
              </small>

              <div className="d-flex gap-2">
                <Button
                  color="light"
                  className="border"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  <ChevronLeft size={16} />
                </Button>

                <Button
                  color="light"
                  className="border"
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => p + 1)}
                >
                  <ChevronRight size={16} />
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>
      </Container>
    </div>
  );
};

export default OrdersPage;
