import React, { useEffect, useMemo, useState } from "react";
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  Search,
  Users,
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
import BreadCrumb from "../../Components/Common/BreadCrumb";

type SiteId = "APC" | "MP" | "PNP";
type Channel = "online" | "walkin" | "callin" | string;

type Customer = {
  id: string;
  customer_name: string;
  customer_email: string;
  site: SiteId;
  channel: Channel;
  orders: number;
  lifetime_value: number;
  date_joined: string;
  last_order: string;
};

type SortKey =
  | "customer_name"
  | "site"
  | "channel"
  | "orders"
  | "lifetime_value"
  | "date_joined"
  | "last_order";

type SortDirection = "asc" | "desc";

const SITES: Array<{ id: SiteId; name: string }> = [
  { id: "APC", name: "American Plaque Co." },
  { id: "MP", name: "Master Plaques" },
  { id: "PNP", name: "Plaques & Patches" },
];

const SITE_COLORS: Record<SiteId, string> = {
  APC: "primary",
  MP: "success",
  PNP: "warning",
};

const CustomersPage: React.FC = () => {
  document.title = "Customers | APC Sales Analytics";

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [siteFilter, setSiteFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [sortKey, setSortKey] = useState<SortKey>("date_joined");
  const [sortDirection, setSortDirection] = useState<SortDirection>("desc");
  const perPage = 10;

  const normalizeSite = (site: any): SiteId => {
    const s = String(site || "").trim().toUpperCase();
    if (s === "APC") return "APC";
    if (s === "MP") return "MP";
    if (s === "PNP") return "PNP";
    return "APC";
  };

  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        setLoading(true);
        const res = await fetch("https://apidb.americanplaquecompany.com/analytics/customers");
        const data = await res.json();

        if (data.status === "success") {
          const rows = Array.isArray(data.customers)
            ? data.customers
            : Array.isArray(data.data)
            ? data.data
            : [];

          const mapped: Customer[] = rows.map((c: any, index: number) => ({
            id: String(c.id ?? c.customer_id ?? index),
            customer_name: String(c.customer_name ?? c.name ?? "Unknown Customer"),
            customer_email: String(c.customer_email ?? c.email ?? ""),
            site: normalizeSite(c.site),
            channel: String(c.channel ?? "online"),
            orders: Number(c.orders ?? c.order_count ?? 0),
            lifetime_value: Number(c.lifetime_value ?? c.total_spent ?? 0),
            date_joined: String(c.date_joined ?? c.created_at ?? c.date_created ?? ""),
            last_order: String(c.last_order ?? c.last_order_date ?? ""),
          }));

          setCustomers(mapped);
        } else {
          setCustomers([]);
        }
      } catch (err) {
        console.error("Error fetching customers:", err);
        setCustomers([]);
      } finally {
        setLoading(false);
      }
    };

    fetchCustomers();
  }, []);

  const filtered = useMemo(() => {
    return customers.filter((c) => {
      const keyword = search.toLowerCase();

      if (
        keyword &&
        !c.customer_name.toLowerCase().includes(keyword) &&
        !c.customer_email.toLowerCase().includes(keyword)
      ) {
        return false;
      }

      if (siteFilter !== "all" && c.site !== siteFilter) {
        return false;
      }

      return true;
    });
  }, [customers, search, siteFilter]);

  useEffect(() => {
    setPage(1);
  }, [search, siteFilter, sortKey, sortDirection]);

  const sorted = useMemo(() => {
    const getValue = (customer: Customer) => {
      switch (sortKey) {
        case "customer_name":
          return customer.customer_name.toLowerCase();
        case "site":
          return customer.site;
        case "channel":
          return String(customer.channel).toLowerCase();
        case "orders":
          return customer.orders;
        case "lifetime_value":
          return customer.lifetime_value;
        case "date_joined":
          return customer.date_joined ? new Date(customer.date_joined).getTime() : 0;
        case "last_order":
          return customer.last_order ? new Date(customer.last_order).getTime() : 0;
        default:
          return customer.customer_name.toLowerCase();
      }
    };

    return [...filtered].sort((a, b) => {
      const aValue = getValue(a);
      const bValue = getValue(b);

      if (aValue < bValue) {
        return sortDirection === "asc" ? -1 : 1;
      }

      if (aValue > bValue) {
        return sortDirection === "asc" ? 1 : -1;
      }

      return 0;
    });
  }, [filtered, sortDirection, sortKey]);

  const totalPages = Math.max(1, Math.ceil(sorted.length / perPage));
  const paged = sorted.slice((page - 1) * perPage, page * perPage);

  const handleSort = (key: SortKey) => {
    if (sortKey === key) {
      setSortDirection((current) => (current === "asc" ? "desc" : "asc"));
      return;
    }

    setSortKey(key);
    setSortDirection("asc");
  };

  const renderSortIcon = (key: SortKey) => {
    if (sortKey !== key) {
      return <ArrowUpDown size={14} className="text-muted" />;
    }

    return sortDirection === "asc" ? <ArrowUp size={14} /> : <ArrowDown size={14} />;
  };

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Customers" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col>
            <div className="d-flex align-items-center gap-2 mb-1">
              <Users size={22} className="text-primary" />
              <h2 className="mb-0">Customers</h2>
            </div>
            {loading ? (
              <p>Loading customers...</p>
            ) : (
              <p className="text-muted mb-0">
                {filtered.length} customers across all channels
              </p>
            )}
          </Col>
        </Row>

        <Row>
          <Col>
            <Card className="border-0 shadow-sm">
              <CardBody>
                <Row className="g-3 mb-4">
                  <Col md={8}>
                    <Label className="form-label">Search</Label>
                    <InputGroup>
                      <InputGroupText>
                        <Search size={16} />
                      </InputGroupText>
                      <Input
                        placeholder="Search customers..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                      />
                    </InputGroup>
                  </Col>

                  <Col md={4}>
                    <Label className="form-label">Sites</Label>
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

	                <div className="table-responsive">
	                  <table className="table table-hover align-middle mb-0">
	                    <thead>
	                      <tr>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("customer_name")}
                            >
                              Customer
                              {renderSortIcon("customer_name")}
                            </button>
                          </th>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("site")}
                            >
                              Site
                              {renderSortIcon("site")}
                            </button>
                          </th>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("channel")}
                            >
                              Channel
                              {renderSortIcon("channel")}
                            </button>
                          </th>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("orders")}
                            >
                              Orders
                              {renderSortIcon("orders")}
                            </button>
                          </th>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("lifetime_value")}
                            >
                              Lifetime Value
                              {renderSortIcon("lifetime_value")}
                            </button>
                          </th>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("date_joined")}
                            >
                              Date Joined
                              {renderSortIcon("date_joined")}
                            </button>
                          </th>
	                        <th>
                            <button
                              type="button"
                              className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                              onClick={() => handleSort("last_order")}
                            >
                              Last Order
                              {renderSortIcon("last_order")}
                            </button>
                          </th>
	                      </tr>
	                    </thead>

                    <tbody>
                      {!loading &&
                        paged.map((c) => (
                          <tr key={c.id}>
                            <td>
                              <div className="fw-medium">{c.customer_name}</div>
                              <small className="text-muted">{c.customer_email}</small>
                            </td>

                            <td>
                              <Badge color={SITE_COLORS[c.site]} className="text-uppercase">
                                {c.site}
                              </Badge>
                            </td>

	                            <td className="text-capitalize">{c.channel}</td>
	                            <td>{c.orders}</td>
	                            <td>${c.lifetime_value.toLocaleString()}</td>
	                            <td>
                              {c.date_joined
                                ? new Date(c.date_joined).toLocaleDateString()
                                : "-"}
                            </td>
	                            <td>
	                              {c.last_order
	                                ? new Date(c.last_order).toLocaleDateString()
	                                : "-"}
                            </td>
                          </tr>
                        ))}

	                      {!loading && paged.length === 0 && (
	                        <tr>
	                          <td colSpan={7} className="text-center text-muted py-4">
	                            No customers found for the selected filters.
	                          </td>
	                        </tr>
	                      )}

	                      {loading && (
	                        <tr>
	                          <td colSpan={7} className="text-center py-4">
	                            <div className="d-inline-flex align-items-center gap-2 text-muted">
                                <Spinner size="sm" color="primary" />
                                <span>Loading customers...</span>
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
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default CustomersPage;
