import React, { useEffect, useMemo, useState } from "react";
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
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";
import { format, startOfMonth, startOfQuarter, startOfYear, subDays } from "date-fns";
import BreadCrumb from "../../Components/Common/BreadCrumb";

type SiteId = "APC" | "MP" | "PNP";
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
  channel: string;
  customer_name: string;
  customer_email: string;
  total: number;
  status: OrderStatus;
  payment_method: string;
  created_at: string;
};

type ChannelMix = {
  channel: string;
  value: number;
  percentage: number;
};

const COLORS = [
  "hsl(222,62%,22%)", // Website
  "hsl(36,95%,52%)",  // Walk-in
  "hsl(142,71%,45%)", // Call-in
  "hsl(217,91%,60%)", // Distributor
  "hsl(340,75%,55%)",
  "hsl(220,9%,46%)",
];

const SITES: Array<{ id: SiteId; name: string }> = [
  { id: "APC", name: "American Plaque Co." },
  { id: "MP", name: "Master Plaques" },
  { id: "PNP", name: "Plaques & Patches" },
];

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

const CHANNEL_ORDER = ["Website", "Walk-in", "Call-in", "Distributor"];

const SalesChannelsPage: React.FC = () => {
  document.title = "Sales Channels | APC Sales Analytics";

  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);

  const [siteFilter, setSiteFilter] = useState<string>("all");
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

  const normalizeChannel = (channel: any): string => {
    const raw = String(channel || "").trim().toLowerCase();

    if (raw === "online" || raw === "website" || raw === "web") return "Website";
    if (raw === "walkin" || raw === "walk-in" || raw === "walk in") return "Walk-in";
    if (raw === "callin" || raw === "call-in" || raw === "call in") return "Call-in";
    if (raw === "distributor" || raw === "dist") return "Distributor";

    if (!raw) return "Website";

    return raw
      .split(/[\s_-]+/)
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ");
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
            channel: normalizeChannel(o.channel),
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

  const filteredOrders = useMemo(() => {
    return orders.filter((o) => {
      if (siteFilter !== "all" && o.site_id !== siteFilter) {
        return false;
      }

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
  }, [orders, siteFilter, fromDate, toDate]);

  const channelMix = useMemo(() => {
    const totals = new Map<string, number>();

    CHANNEL_ORDER.forEach((channel) => {
      totals.set(channel, 0);
    });

    filteredOrders.forEach((order) => {
      const channel = normalizeChannel(order.channel);
      totals.set(channel, (totals.get(channel) || 0) + Number(order.total || 0));
    });

    const grandTotal = Array.from(totals.values()).reduce((sum, value) => sum + value, 0);

    const ordered = Array.from(totals.entries())
      .map(([channel, value]) => ({
        channel,
        value,
        percentage: grandTotal > 0 ? (value / grandTotal) * 100 : 0,
      }))
      .filter((item) => item.value > 0 || CHANNEL_ORDER.includes(item.channel))
      .sort((a, b) => {
        const ai = CHANNEL_ORDER.indexOf(a.channel);
        const bi = CHANNEL_ORDER.indexOf(b.channel);
        if (ai === -1 && bi === -1) return a.channel.localeCompare(b.channel);
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
      });

    return ordered;
  }, [filteredOrders]);

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Sales Channels" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col>
            <h2 className="mb-1">Sales Channels</h2>
            <p className="text-muted mb-0">
              Website vs Walk-in vs Call-in vs Distributor performance
            </p>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          <Col lg={3}>
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

          <Col lg={3}>
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

          <Col lg={3}>
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

          <Col lg={3}>
            <Label className="form-label">Site</Label>
            <Input
              type="select"
              value={siteFilter}
              onChange={(e) => setSiteFilter(e.target.value)}
            >
              <option value="all">All Sites</option>
              {SITES.map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </Input>
          </Col>
        </Row>

        {loading ? (
          <Card className="border-0 shadow-sm">
            <CardBody className="py-5">
              <div className="d-flex flex-column align-items-center justify-content-center gap-3">
                <Spinner color="primary" />
                <div className="text-muted">Loading sales channels...</div>
              </div>
            </CardBody>
          </Card>
        ) : (
          <>
            <Row className="g-3 mb-4">
              {channelMix.map((ch, i) => (
                <Col md={6} xl={3} key={ch.channel}>
                  <Card className="border-0 shadow-sm h-100">
                    <CardBody className="p-4">
                      <div className="d-flex align-items-center gap-2 mb-3">
                        <span
                          className="rounded-circle d-inline-block"
                          style={{
                            width: 12,
                            height: 12,
                            backgroundColor: COLORS[i % COLORS.length],
                          }}
                        />
                        <span className="fw-medium">{ch.channel}</span>
                      </div>

                      <h3 className="mb-1">
                        $
                        {ch.value.toLocaleString(undefined, {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        })}
                      </h3>

                      <small className="text-muted">
                        {ch.percentage.toFixed(1)}% of total revenue
                      </small>
                    </CardBody>
                  </Card>
                </Col>
              ))}
            </Row>

            <Row>
              <Col>
                <Card className="border-0 shadow-sm">
                  <CardHeader className="border-0 pb-0">
                    <h6 className="mb-0">Channel Distribution</h6>
                  </CardHeader>

                  <CardBody style={{ height: 340 }}>
                    {channelMix.every((item) => item.value === 0) ? (
                      <div className="d-flex align-items-center justify-content-center h-100 text-muted">
                        No channel data found for the selected filters.
                      </div>
                    ) : (
                      <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                          <Pie
                            data={channelMix.filter((item) => item.value > 0)}
                            dataKey="value"
                            nameKey="channel"
                            cx="50%"
                            cy="50%"
                            innerRadius={60}
                            outerRadius={100}
                            paddingAngle={3}
                            label={({ channel, percentage }: any) =>
                              `${channel} ${Number(percentage).toFixed(1)}%`
                            }
                          >
                            {channelMix
                              .filter((item) => item.value > 0)
                              .map((_, i) => (
                                <Cell key={i} fill={COLORS[i % COLORS.length]} />
                              ))}
                          </Pie>

                          <Tooltip
                            formatter={(v: number) => [
                              `$${v.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                              })}`,
                              "Revenue",
                            ]}
                          />
                        </PieChart>
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

export default SalesChannelsPage;