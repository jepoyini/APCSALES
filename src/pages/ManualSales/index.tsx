import React, { useEffect, useMemo, useState } from "react";
import Swal from "sweetalert2";
import { ChevronLeft, ChevronRight, Edit, Plus, Search } from "lucide-react";
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  Col,
  Container,
  Input,
  InputGroup,
  InputGroupText,
  Label,
  Modal,
  ModalBody,
  ModalHeader,
  Row,
  Spinner,
} from "reactstrap";
import BreadCrumb from "../../Components/Common/BreadCrumb";
import { usePermissions } from "../../Components/Hooks/UserHooks";
import { APP_PERMISSIONS } from "../../helpers/permissions";

type SiteId = "APC" | "MP" | "PNP";
type Channel = "website" | "walkin" | "callin" | "distributor";
type Status =
  | "Completed"
  | "Processing"
  | "Pending"
  | "Refunded"
  | "Cancelled"
  | "On-Hold";

type ManualSale = {
  id: string;
  order_number: string;
  customer_name: string;
  customer_email: string;
  channel: Channel;
  site_attribution: SiteId;
  total: number;
  payment_method: string;
  status: Status;
  created_at: string;
};

const API_BASE = "https://apidb.americanplaquecompany.com/analytics";

const SITES: Array<{ id: SiteId; name: string }> = [
  { id: "APC", name: "American Plaque Co." },
  { id: "MP", name: "Master Plaques" },
  { id: "PNP", name: "Plaques & Patches" },
];

const CHANNEL_OPTIONS: Array<{ id: Channel; label: string }> = [
  { id: "website", label: "Website" },
  { id: "walkin", label: "Walk-in" },
  { id: "callin", label: "Call-in" },
  { id: "distributor", label: "Distributor" },
];

const STATUS_OPTIONS: Status[] = [
  "Completed",
  "Processing",
  "Pending",
  "Refunded",
  "Cancelled",
  "On-Hold",
];

const SITE_COLORS: Record<SiteId, string> = {
  APC: "primary",
  MP: "success",
  PNP: "warning",
};

const CHANNEL_COLORS: Record<Channel, string> = {
  website: "primary",
  walkin: "warning",
  callin: "info",
  distributor: "success",
};

const STATUS_COLORS: Record<Status, string> = {
  Completed: "success",
  Processing: "info",
  Pending: "warning",
  Refunded: "danger",
  Cancelled: "secondary",
  "On-Hold": "secondary",
};

const PAYMENT_OPTIONS = [
  "Cash",
  "Credit Card",
  "Check",
  "PayPal",
  "Wire Transfer",
];

const ManualSalesPage: React.FC = () => {
  document.title = "Manual Sales | APC Sales Analytics";
  const { hasPermission } = usePermissions();

  const [dialogOpen, setDialogOpen] = useState(false);
  const [sales, setSales] = useState<ManualSale[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [search, setSearch] = useState("");
  const [siteFilter, setSiteFilter] = useState("all");
  const [channelFilter, setChannelFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");

  const [page, setPage] = useState(1);
  const perPage = 10;

  const [editingId, setEditingId] = useState<string | null>(null);

  const [orderNumber, setOrderNumber] = useState("");
  const [customerName, setCustomerName] = useState("");
  const [customerEmail, setCustomerEmail] = useState("");
  const [channel, setChannel] = useState<Channel>("walkin");
  const [site, setSite] = useState<SiteId>("APC");
  const [total, setTotal] = useState(0);
  const [paymentMethod, setPaymentMethod] = useState("Cash");
  const [status, setStatus] = useState<Status>("Completed");
  const [createdAt, setCreatedAt] = useState(() =>
    new Date().toISOString().slice(0, 10)
  );

  const normalizeSite = (site: any): SiteId => {
    const s = String(site || "").trim().toUpperCase();
    if (s === "APC") return "APC";
    if (s === "MP") return "MP";
    if (s === "PNP") return "PNP";
    return "APC";
  };

  const normalizeChannel = (channel: any): Channel => {
    const s = String(channel || "").trim().toLowerCase();
    if (s === "website" || s === "online" || s === "web") return "website";
    if (s === "walkin" || s === "walk-in" || s === "walk in") return "walkin";
    if (s === "callin" || s === "call-in" || s === "call in") return "callin";
    if (s === "distributor" || s === "dist") return "distributor";
    return "walkin";
  };

  const normalizeStatus = (status: any): Status => {
    const s = String(status || "").trim().toLowerCase();
    if (s === "completed") return "Completed";
    if (s === "processing") return "Processing";
    if (s === "pending") return "Pending";
    if (s === "refunded") return "Refunded";
    if (s === "cancelled" || s === "canceled") return "Cancelled";
    if (s === "on-hold" || s === "on hold") return "On-Hold";
    return "Completed";
  };

  const formatChannelLabel = (value: Channel) => {
    switch (value) {
      case "website":
        return "Website";
      case "walkin":
        return "Walk-in";
      case "callin":
        return "Call-in";
      case "distributor":
        return "Distributor";
      default:
        return value;
    }
  };

  const resetForm = () => {
    setEditingId(null);
    setOrderNumber("");
    setCustomerName("");
    setCustomerEmail("");
    setChannel("walkin");
    setSite("APC");
    setTotal(0);
    setPaymentMethod("Cash");
    setStatus("Completed");
    setCreatedAt(new Date().toISOString().slice(0, 10));
  };

  const openAddModal = () => {
    resetForm();
    setDialogOpen(true);
  };

  const openEditModal = (sale: ManualSale) => {
    setEditingId(sale.id);
    setOrderNumber(sale.order_number);
    setCustomerName(sale.customer_name);
    setCustomerEmail(sale.customer_email);
    setChannel(sale.channel);
    setSite(sale.site_attribution);
    setTotal(sale.total);
    setPaymentMethod(sale.payment_method);
    setStatus(sale.status);
    setCreatedAt(
      sale.created_at
        ? new Date(sale.created_at).toISOString().slice(0, 10)
        : new Date().toISOString().slice(0, 10)
    );
    setDialogOpen(true);
  };

  useEffect(() => {
    const fetchSales = async () => {
      try {
        setLoading(true);
        const res = await fetch(`${API_BASE}/orders`);
        const data = await res.json();

        if (data.status === "success") {
          const rows = Array.isArray(data.orders)
            ? data.orders
            : Array.isArray(data.data)
            ? data.data
            : [];

          const mapped: ManualSale[] = rows.map((o: any, index: number) => ({
            id: String(o.id ?? o.order_id ?? index),
            order_number: String(
              o.order_number ?? o.reference_no ?? `ORD-${1000 + index}`
            ),
            customer_name: String(o.customer_name ?? "Unknown Customer"),
            customer_email: String(o.customer_email ?? ""),
            channel: normalizeChannel(o.channel),
            site_attribution: normalizeSite(o.site ?? o.site_id),
            total: Number(o.total ?? o.order_total ?? o.amount ?? 0),
            payment_method: String(o.payment_method ?? "Cash"),
            status: normalizeStatus(o.status),
            created_at: String(
              o.order_date ?? o.created_at ?? o.date_created ?? new Date().toISOString()
            ),
          }));

          setSales(mapped);
        } else {
          setSales([]);
        }
      } catch (err) {
        console.error("Error fetching manual sales:", err);
        setSales([]);
      } finally {
        setLoading(false);
      }
    };

    fetchSales();
  }, []);

  const filteredSales = useMemo(() => {
    const q = search.trim().toLowerCase();

    const filtered = sales.filter((s) => {
      if (
        q &&
        !s.order_number.toLowerCase().includes(q) &&
        !s.customer_name.toLowerCase().includes(q) &&
        !s.customer_email.toLowerCase().includes(q)
      ) {
        return false;
      }

      if (siteFilter !== "all" && s.site_attribution !== siteFilter) {
        return false;
      }

      if (channelFilter !== "all" && s.channel !== channelFilter) {
        return false;
      }

      if (statusFilter !== "all" && s.status !== statusFilter) {
        return false;
      }

      return true;
    });

    return [...filtered].sort((a, b) => {
      const aTime = a.created_at ? new Date(a.created_at).getTime() : 0;
      const bTime = b.created_at ? new Date(b.created_at).getTime() : 0;
      return bTime - aTime;
    });
  }, [sales, search, siteFilter, channelFilter, statusFilter]);

  useEffect(() => {
    setPage(1);
  }, [search, siteFilter, channelFilter, statusFilter]);

  const totalPages = Math.max(1, Math.ceil(filteredSales.length / perPage));
  const pagedSales = filteredSales.slice((page - 1) * perPage, page * perPage);

  const handleSave = async () => {
    const requiredPermission = editingId
      ? APP_PERMISSIONS.manualSalesEdit
      : APP_PERMISSIONS.manualSalesCreate;

    if (!hasPermission(requiredPermission)) {
      Swal.fire({
        icon: "warning",
        text: "You do not have permission to save manual sales.",
        confirmButtonText: "OK",
      });
      return;
    }

    if (!customerName.trim()) {
      Swal.fire({
        icon: "warning",
        text: "Customer name is required.",
        confirmButtonText: "OK",
      });
      return;
    }

    if (!orderNumber.trim()) {
      Swal.fire({
        icon: "warning",
        text: "Order number is required.",
        confirmButtonText: "OK",
      });
      return;
    }

    if (Number(total) <= 0) {
      Swal.fire({
        icon: "warning",
        text: "Total must be greater than 0.",
        confirmButtonText: "OK",
      });
      return;
    }

    const payload = {
      ...(editingId ? { id: Number(editingId) } : {}),
      order_number: orderNumber.trim(),
      customer_name: customerName.trim(),
      customer_email: customerEmail.trim(),
      site,
      channel,
      total: Number(total),
      status,
      payment_method: paymentMethod,
      order_date: createdAt || new Date().toISOString().slice(0, 10),
    };

    try {
      setSaving(true);

      const url = editingId
        ? `${API_BASE}/orders/update`
        : `${API_BASE}/orders/add`;

      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (data.status !== "success") {
        Swal.fire({
          icon: "error",
          text: data.message || "Failed to save manual sale.",
          confirmButtonText: "OK",
        });
        return;
      }

      const returned = data.order ?? payload;

      const savedSale: ManualSale = {
        id: String(returned.id ?? editingId ?? Date.now()),
        order_number: String(returned.order_number ?? payload.order_number),
        customer_name: String(returned.customer_name ?? payload.customer_name),
        customer_email: String(returned.customer_email ?? payload.customer_email),
        channel: normalizeChannel(returned.channel ?? payload.channel),
        site_attribution: normalizeSite(returned.site ?? payload.site),
        total: Number(returned.total ?? payload.total),
        payment_method: String(returned.payment_method ?? payload.payment_method),
        status: normalizeStatus(returned.status ?? payload.status),
        created_at: String(
          returned.order_date ??
            returned.created_at ??
            payload.order_date
        ),
      };

      if (editingId) {
        setSales((prev) =>
          prev.map((sale) => (sale.id === editingId ? savedSale : sale))
        );

        Swal.fire({
          icon: "success",
          text: data.message || "Manual sale updated.",
          confirmButtonText: "OK",
        });
      } else {
        setSales((prev) => [savedSale, ...prev]);

        Swal.fire({
          icon: "success",
          text: data.message || "Manual sale added.",
          confirmButtonText: "OK",
        });
      }

      setDialogOpen(false);
      resetForm();
    } catch (err) {
      console.error("Save error:", err);
      Swal.fire({
        icon: "error",
        text: "Failed to save manual sale.",
        confirmButtonText: "OK",
      });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Manual Sales" pageTitle="Dashboard" />

        <Row className="mb-4 align-items-center">
          <Col md={8}>
            <h2 className="mb-1">Manual Sales</h2>
            <p className="text-muted mb-0">
              Manual sales entries aligned with orders reporting
            </p>
          </Col>

          <Col md={4} className="text-md-end mt-3 mt-md-0">
            <Button
              color="primary"
              className="d-inline-flex align-items-center gap-2"
              onClick={openAddModal}
              disabled={!hasPermission(APP_PERMISSIONS.manualSalesCreate)}
            >
              <Plus size={14} /> New Sale
            </Button>
          </Col>
        </Row>

        <Card className="border-0 shadow-sm">
          <CardHeader className="border-0 pb-0">
            <Row className="g-3">
              <Col lg={4}>
                <Label className="form-label">Search</Label>
                <InputGroup>
                  <InputGroupText>
                    <Search size={16} />
                  </InputGroupText>
                  <Input
                    placeholder="Search by order, customer, or email..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                  />
                </InputGroup>
              </Col>

              <Col lg={3}>
                <Label className="form-label">Site</Label>
                <Input
                  type="select"
                  value={siteFilter}
                  onChange={(e) => setSiteFilter(e.target.value)}
                >
                  <option value="all">All Sites</option>
                  {SITES.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col lg={2}>
                <Label className="form-label">Channel</Label>
                <Input
                  type="select"
                  value={channelFilter}
                  onChange={(e) => setChannelFilter(e.target.value)}
                >
                  <option value="all">All Channels</option>
                  {CHANNEL_OPTIONS.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.label}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col lg={3}>
                <Label className="form-label">Status</Label>
                <Input
                  type="select"
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                >
                  <option value="all">All Statuses</option>
                  {STATUS_OPTIONS.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </Input>
              </Col>
            </Row>
          </CardHeader>

          <CardBody>
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Channel</th>
                    <th>Site</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                  </tr>
                </thead>

                <tbody>
                  {loading && (
                    <tr>
                      <td colSpan={9} className="text-center py-4">
                        <div className="d-inline-flex align-items-center gap-2 text-muted">
                          <Spinner size="sm" color="primary" />
                          <span>Loading manual sales...</span>
                        </div>
                      </td>
                    </tr>
                  )}

                  {!loading &&
                    pagedSales.map((sale) => (
                      <tr key={sale.id}>
                        <td className="fw-semibold">{sale.order_number}</td>

                        <td>
                          <div className="fw-medium">{sale.customer_name}</div>
                          {sale.customer_email && (
                            <small className="text-muted">{sale.customer_email}</small>
                          )}
                        </td>

                        <td>
                          <Badge
                            color={CHANNEL_COLORS[sale.channel]}
                            className="text-capitalize"
                          >
                            {formatChannelLabel(sale.channel)}
                          </Badge>
                        </td>

                        <td>
                          <Badge
                            color={SITE_COLORS[sale.site_attribution]}
                            className="text-uppercase"
                          >
                            {sale.site_attribution}
                          </Badge>
                        </td>

                        <td>
                          $
                          {sale.total.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          })}
                        </td>

                        <td>{sale.payment_method}</td>

                        <td>
                          <Badge color={STATUS_COLORS[sale.status]}>
                            {sale.status}
                          </Badge>
                        </td>

                        <td>{new Date(sale.created_at).toLocaleDateString()}</td>

                        <td>
                          <Button
                            color="light"
                            className="border btn-sm p-1"
                            onClick={() => openEditModal(sale)}
                            disabled={!hasPermission(APP_PERMISSIONS.manualSalesEdit)}
                          >
                            <Edit size={14} />
                          </Button>
                        </td>
                      </tr>
                    ))}

                  {!loading && pagedSales.length === 0 && (
                    <tr>
                      <td colSpan={9} className="text-center text-muted py-4">
                        No manual sales found for the selected filters.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {!loading && (
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
            )}
          </CardBody>
        </Card>

        <Modal
          isOpen={dialogOpen}
          toggle={() => !saving && setDialogOpen(false)}
          size="lg"
          centered
        >
          <ModalHeader toggle={() => !saving && setDialogOpen(false)}>
            {editingId ? "Edit Manual Sale" : "Add Manual Sale"}
          </ModalHeader>

          <ModalBody>
            <Row className="g-3">
              <Col md={6}>
                <Label className="form-label">Order Number *</Label>
                <Input
                  value={orderNumber}
                  onChange={(e) => setOrderNumber(e.target.value)}
                  placeholder="ORD-1001"
                  disabled={saving}
                />
              </Col>

              <Col md={6}>
                <Label className="form-label">Customer Name *</Label>
                <Input
                  value={customerName}
                  onChange={(e) => setCustomerName(e.target.value)}
                  placeholder="Full name"
                  disabled={saving}
                />
              </Col>

              <Col md={6}>
                <Label className="form-label">Customer Email</Label>
                <Input
                  type="email"
                  value={customerEmail}
                  onChange={(e) => setCustomerEmail(e.target.value)}
                  placeholder="name@email.com"
                  disabled={saving}
                />
              </Col>

              <Col md={6}>
                <Label className="form-label">Channel *</Label>
                <Input
                  type="select"
                  value={channel}
                  onChange={(e) => setChannel(e.target.value as Channel)}
                  disabled={saving}
                >
                  {CHANNEL_OPTIONS.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.label}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col md={6}>
                <Label className="form-label">Site Attribution *</Label>
                <Input
                  type="select"
                  value={site}
                  onChange={(e) => setSite(e.target.value as SiteId)}
                  disabled={saving}
                >
                  {SITES.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col md={6}>
                <Label className="form-label">Total *</Label>
                <Input
                  type="number"
                  min={0}
                  step="0.01"
                  value={total}
                  onChange={(e) => setTotal(Number(e.target.value) || 0)}
                  disabled={saving}
                />
              </Col>

              <Col md={6}>
                <Label className="form-label">Payment Method</Label>
                <Input
                  type="select"
                  value={paymentMethod}
                  onChange={(e) => setPaymentMethod(e.target.value)}
                  disabled={saving}
                >
                  {PAYMENT_OPTIONS.map((p) => (
                    <option key={p} value={p}>
                      {p}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col md={6}>
                <Label className="form-label">Status</Label>
                <Input
                  type="select"
                  value={status}
                  onChange={(e) => setStatus(e.target.value as Status)}
                  disabled={saving}
                >
                  {STATUS_OPTIONS.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </Input>
              </Col>

              <Col md={12}>
                <Label className="form-label">Order Date</Label>
                <Input
                  type="date"
                  value={createdAt}
                  onChange={(e) => setCreatedAt(e.target.value)}
                  disabled={saving}
                />
              </Col>

              <Col
                md={12}
                className="d-flex justify-content-end align-items-center pt-2 border-top"
              >
                <div className="d-flex gap-2">
                  <Button
                    color="light"
                    className="border"
                    onClick={() => setDialogOpen(false)}
                    disabled={saving}
                  >
                    Cancel
                  </Button>

                  <Button
                    color="primary"
                    onClick={handleSave}
                    disabled={
                      saving ||
                      !hasPermission(
                        editingId
                          ? APP_PERMISSIONS.manualSalesEdit
                          : APP_PERMISSIONS.manualSalesCreate
                      )
                    }
                  >
                    {saving ? (
                      <span className="d-inline-flex align-items-center gap-2">
                        <Spinner size="sm" />
                        Saving...
                      </span>
                    ) : editingId ? (
                      "Update Sale"
                    ) : (
                      "Save Sale"
                    )}
                  </Button>
                </div>
              </Col>
            </Row>
          </ModalBody>
        </Modal>
      </Container>
    </div>
  );
};

export default ManualSalesPage;
