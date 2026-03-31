import React, { useEffect, useMemo, useState } from "react";
import { Globe, ShoppingCart, TrendingUp } from "lucide-react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
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
import { endOfYear, format, startOfMonth, startOfQuarter, startOfYear, subDays, subYears } from "date-fns";
import BreadCrumb from "../../Components/Common/BreadCrumb";

type SiteId = "APC" | "MP" | "PNP";

type Order = {
  id: string;
  order_number: string;
  site_id: SiteId;
  total: number;
  created_at: string;
};

type RevenueByWebsite = {
  site: string;
  revenue: number;
  orders: number;
  color: string;
};

const SITE_META: Record<
  SiteId,
  { label: string; color: string }
> = {
  APC: {
    label: "American Plaque Co.",
    color: "hsl(222, 62%, 22%)",
  },
  MP: {
    label: "Master Plaques",
    color: "hsl(36, 95%, 52%)",
  },
  PNP: {
    label: "Plaques & Patches",
    color: "hsl(142, 71%, 45%)",
  },
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
  {
    label: "Last Year",
    from: format(startOfYear(subYears(new Date(), 1)), "yyyy-MM-dd"),
    to: format(endOfYear(subYears(new Date(), 1)), "yyyy-MM-dd"),
  },
];

const SITE_ORDER: SiteId[] = ["APC", "MP", "PNP"];

const WebsitePerformancePage: React.FC = () => {
  document.title = "Website Performance | APC Sales Analytics";

  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);

  const [datePreset, setDatePreset] = useState("Last 30 Days");
  const [fromDate, setFromDate] = useState(format(subDays(new Date(), 29), "yyyy-MM-dd"));
  const [toDate, setToDate] = useState(format(new Date(), "yyyy-MM-dd"));

  const normalizeSite = (site: any): SiteId => {
    const s = String(site || "").trim().toUpperCase();
    if (s === "APC") return "APC";
    if (s === "MP") return "MP";
    if (s === "PNP") return "PNP";
    return "APC";
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
            total: Number(o.total ?? o.order_total ?? o.amount ?? 0),
            created_at: String(o.order_date ?? o.created_at ?? o.date_created ?? ""),
          }));

          setOrders(mapped);
        } else {
          setOrders([]);
        }
      } catch (err) {
        console.error("Error fetching website performance data:", err);
        setOrders([]);
      } finally {
        setLoading(false);
      }
    };

    fetchOrders();
  }, []);

  const filteredOrders = useMemo(() => {
    return orders.filter((o) => {
      const orderDate = new Date(o.created_at);
      if (Number.isNaN(orderDate.getTime())) return false;

      if (fromDate) {
        const from = new Date(fromDate);
        from.setHours(0, 0, 0, 0);
        if (orderDate < from) return false;
      }

      if (toDate) {
        const to = new Date(toDate);
        to.setHours(23, 59, 59, 999);
        if (orderDate > to) return false;
      }

      return true;
    });
  }, [orders, fromDate, toDate]);

  const revenueByWebsite = useMemo<RevenueByWebsite[]>(() => {
    const totals = new Map<SiteId, { revenue: number; orders: number }>();

    SITE_ORDER.forEach((site) => {
      totals.set(site, { revenue: 0, orders: 0 });
    });

    filteredOrders.forEach((order) => {
      const site = normalizeSite(order.site_id);
      const current = totals.get(site) || { revenue: 0, orders: 0 };

      totals.set(site, {
        revenue: current.revenue + Number(order.total || 0),
        orders: current.orders + 1,
      });
    });

    return SITE_ORDER.map((site) => ({
      site: SITE_META[site].label,
      revenue: totals.get(site)?.revenue || 0,
      orders: totals.get(site)?.orders || 0,
      color: SITE_META[site].color,
    }));
  }, [filteredOrders]);

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Website Performance" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col>
            <h2 className="mb-1">Website Performance</h2>
            <p className="text-muted mb-0">Per-site and combined metrics</p>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          <Col lg={4}>
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

          <Col lg={4}>
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

          <Col lg={4}>
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
        </Row>

        {loading ? (
          <Card className="border-0 shadow-sm">
            <CardBody className="py-5">
              <div className="d-flex flex-column align-items-center justify-content-center gap-3">
                <Spinner color="primary" />
                <div className="text-muted">Loading website performance...</div>
              </div>
            </CardBody>
          </Card>
        ) : (
          <>
            <Row className="g-3 mb-4">
              {revenueByWebsite.map((site) => (
                <Col md={4} key={site.site}>
                  <Card
                    className="border-0 shadow-sm h-100"
                    style={{ borderLeft: `4px solid ${site.color}` }}
                  >
                    <CardBody className="p-4">
                      <div className="d-flex align-items-center gap-2 mb-3">
                        <Globe size={16} className="text-muted" />
                        <span className="fw-semibold">{site.site}</span>
                      </div>

                      <div className="d-flex justify-content-between align-items-center mb-2">
                        <small className="text-muted d-flex align-items-center gap-1">
                          <TrendingUp size={13} /> Revenue
                        </small>
                        <strong>
                          $
                          {site.revenue.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          })}
                        </strong>
                      </div>

                      <div className="d-flex justify-content-between align-items-center mb-2">
                        <small className="text-muted d-flex align-items-center gap-1">
                          <ShoppingCart size={13} /> Orders
                        </small>
                        <strong>{site.orders.toLocaleString()}</strong>
                      </div>

                      <div className="d-flex justify-content-between align-items-center">
                        <small className="text-muted">AOV</small>
                        <strong>
                          $
                          {(site.orders > 0 ? site.revenue / site.orders : 0).toLocaleString(
                            undefined,
                            {
                              minimumFractionDigits: 2,
                              maximumFractionDigits: 2,
                            }
                          )}
                        </strong>
                      </div>
                    </CardBody>
                  </Card>
                </Col>
              ))}
            </Row>

            <Row>
              <Col>
                <Card className="border-0 shadow-sm">
                  <CardHeader className="border-0 pb-0">
                    <h6 className="mb-0">Revenue Comparison</h6>
                  </CardHeader>
                  <CardBody style={{ height: 320 }}>
                    {revenueByWebsite.every((site) => site.revenue === 0) ? (
                      <div className="d-flex align-items-center justify-content-center h-100 text-muted">
                        No website performance data found for the selected date range.
                      </div>
                    ) : (
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={revenueByWebsite}>
                          <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                          <XAxis dataKey="site" tick={{ fontSize: 11 }} />
                          <YAxis
                            tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
                            tick={{ fontSize: 11 }}
                          />
                          <Tooltip
                            formatter={(v: number) => [
                              `$${v.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                              })}`,
                              "Revenue",
                            ]}
                          />
                          <Bar dataKey="revenue" radius={[4, 4, 0, 0]}>
                            {revenueByWebsite.map((entry, i) => (
                              <Cell key={i} fill={entry.color} />
                            ))}
                          </Bar>
                        </BarChart>
                      </ResponsiveContainer>
                    )}
                  </CardBody>
                </Card>
              </Col>
            </Row>
          </>
        )}
      </Container>
    </div>
  );
};

export default WebsitePerformancePage;
