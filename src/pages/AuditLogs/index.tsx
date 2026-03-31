import React, { useEffect, useMemo, useState } from "react";
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  Search,
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

type AuditLog = {
  id: string;
  user_name: string;
  action: string;
  resource: string;
  details: string;
  ip_address: string;
  created_at: string;
};

type SortKey =
  | "created_at"
  | "user_name"
  | "action"
  | "resource"
  | "details"
  | "ip_address";

type SortDirection = "asc" | "desc";

const ACTION_COLORS: Record<string, string> = {
  login: "success",
  login_failed: "danger",
  "manual_sale.create": "info",
  "manual_sale.update": "warning",
  "user.update": "primary",
  "integration.sync": "secondary",
  "role.update": "primary",
};

const API_URL = "https://apidb.americanplaquecompany.com/analytics/auditlogs";

const AuditLogsPage: React.FC = () => {
  document.title = "Audit Logs | APC Sales Analytics";

  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [actionFilter, setActionFilter] = useState("all");
  const [resourceFilter, setResourceFilter] = useState("all");
  const [sortKey, setSortKey] = useState<SortKey>("created_at");
  const [sortDirection, setSortDirection] = useState<SortDirection>("desc");
  const [page, setPage] = useState(1);
  const perPage = 10;

  useEffect(() => {
    const fetchAuditLogs = async () => {
      try {
        setLoading(true);
        const response = await fetch(API_URL);
        const data = await response.json();

        const rows = Array.isArray(data?.auditlogs)
          ? data.auditlogs
          : Array.isArray(data?.logs)
          ? data.logs
          : Array.isArray(data?.data)
          ? data.data
          : Array.isArray(data)
          ? data
          : [];

        const mapped: AuditLog[] = rows.map((log: any, index: number) => ({
          id: String(log.id ?? log.audit_log_id ?? index),
          user_name: String(log.user_name ?? log.name ?? log.user ?? "Unknown User"),
          action: String(log.action ?? log.event ?? "unknown"),
          resource: String(log.resource ?? log.module ?? "general"),
          details: String(log.details ?? log.description ?? log.message ?? ""),
          ip_address: String(log.ip_address ?? log.ip ?? "-"),
          created_at: String(log.created_at ?? log.timestamp ?? log.date_created ?? ""),
        }));

        setLogs(mapped);
      } catch (error) {
        console.error("Error fetching audit logs:", error);
        setLogs([]);
      } finally {
        setLoading(false);
      }
    };

    fetchAuditLogs();
  }, []);

  const actionOptions = useMemo(() => {
    return Array.from(
      new Set(logs.map((log) => log.action).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b));
  }, [logs]);

  const resourceOptions = useMemo(() => {
    return Array.from(
      new Set(logs.map((log) => log.resource).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b));
  }, [logs]);

  const filtered = useMemo(() => {
    const keyword = search.trim().toLowerCase();

    return logs.filter((log) => {
      if (
        keyword &&
        !log.user_name.toLowerCase().includes(keyword) &&
        !log.action.toLowerCase().includes(keyword) &&
        !log.resource.toLowerCase().includes(keyword) &&
        !log.details.toLowerCase().includes(keyword) &&
        !log.ip_address.toLowerCase().includes(keyword)
      ) {
        return false;
      }

      if (actionFilter !== "all" && log.action !== actionFilter) {
        return false;
      }

      if (resourceFilter !== "all" && log.resource !== resourceFilter) {
        return false;
      }

      return true;
    });
  }, [actionFilter, logs, resourceFilter, search]);

  const sorted = useMemo(() => {
    const getValue = (log: AuditLog) => {
      switch (sortKey) {
        case "created_at":
          return log.created_at ? new Date(log.created_at).getTime() : 0;
        case "user_name":
          return log.user_name.toLowerCase();
        case "action":
          return log.action.toLowerCase();
        case "resource":
          return log.resource.toLowerCase();
        case "details":
          return log.details.toLowerCase();
        case "ip_address":
          return log.ip_address.toLowerCase();
        default:
          return log.created_at ? new Date(log.created_at).getTime() : 0;
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

  useEffect(() => {
    setPage(1);
  }, [search, actionFilter, resourceFilter, sortKey, sortDirection]);

  const totalPages = Math.max(1, Math.ceil(sorted.length / perPage));
  const paged = sorted.slice((page - 1) * perPage, page * perPage);

  const handleSort = (key: SortKey) => {
    if (sortKey === key) {
      setSortDirection((current) => (current === "asc" ? "desc" : "asc"));
      return;
    }

    setSortKey(key);
    setSortDirection(key === "created_at" ? "desc" : "asc");
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
        <BreadCrumb title="Audit Logs" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col>
            <h2 className="mb-1">Audit Logs</h2>
            {loading ? (
              <p className="text-muted mb-0 d-inline-flex align-items-center gap-2">
                <Spinner size="sm" color="primary" />
                <span>Loading audit logs...</span>
              </p>
            ) : (
              <p className="text-muted mb-0">{filtered.length} log entries found</p>
            )}
          </Col>
        </Row>

        <Row>
          <Col>
            <Card className="border-0 shadow-sm">
              <CardBody>
                <Row className="g-3 mb-4">
                  <Col md={6}>
                    <Label className="form-label">Search</Label>
                    <InputGroup>
                      <InputGroupText>
                        <Search size={16} />
                      </InputGroupText>
                      <Input
                        placeholder="Search audit logs..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                      />
                    </InputGroup>
                  </Col>

                  <Col md={3}>
                    <Label className="form-label">Action</Label>
                    <Input
                      type="select"
                      value={actionFilter}
                      onChange={(e) => setActionFilter(e.target.value)}
                    >
                      <option value="all">All Actions</option>
                      {actionOptions.map((action) => (
                        <option key={action} value={action}>
                          {action}
                        </option>
                      ))}
                    </Input>
                  </Col>

                  <Col md={3}>
                    <Label className="form-label">Resource</Label>
                    <Input
                      type="select"
                      value={resourceFilter}
                      onChange={(e) => setResourceFilter(e.target.value)}
                    >
                      <option value="all">All Resources</option>
                      {resourceOptions.map((resource) => (
                        <option key={resource} value={resource}>
                          {resource}
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
                            onClick={() => handleSort("created_at")}
                          >
                            Timestamp
                            {renderSortIcon("created_at")}
                          </button>
                        </th>
                        <th>
                          <button
                            type="button"
                            className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                            onClick={() => handleSort("user_name")}
                          >
                            User
                            {renderSortIcon("user_name")}
                          </button>
                        </th>
                        <th>
                          <button
                            type="button"
                            className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                            onClick={() => handleSort("action")}
                          >
                            Action
                            {renderSortIcon("action")}
                          </button>
                        </th>
                        <th>
                          <button
                            type="button"
                            className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                            onClick={() => handleSort("resource")}
                          >
                            Resource
                            {renderSortIcon("resource")}
                          </button>
                        </th>
                        <th>
                          <button
                            type="button"
                            className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                            onClick={() => handleSort("details")}
                          >
                            Details
                            {renderSortIcon("details")}
                          </button>
                        </th>
                        <th>
                          <button
                            type="button"
                            className="btn btn-link p-0 text-decoration-none text-reset fw-semibold d-inline-flex align-items-center gap-1"
                            onClick={() => handleSort("ip_address")}
                          >
                            IP Address
                            {renderSortIcon("ip_address")}
                          </button>
                        </th>
                      </tr>
                    </thead>

                    <tbody>
                      {!loading &&
                        paged.map((log) => (
                          <tr key={log.id}>
                            <td>
                              <code>
                                {log.created_at
                                  ? new Date(log.created_at).toLocaleString()
                                  : "-"}
                              </code>
                            </td>
                            <td className="fw-medium">{log.user_name}</td>
                            <td>
                              <Badge color={ACTION_COLORS[log.action] || "light"}>
                                {log.action}
                              </Badge>
                            </td>
                            <td className="text-capitalize">{log.resource}</td>
                            <td className="text-muted">{log.details || "-"}</td>
                            <td>
                              <code>{log.ip_address}</code>
                            </td>
                          </tr>
                        ))}

                      {loading && (
                        <tr>
                          <td colSpan={6} className="text-center py-4">
                            <div className="d-inline-flex align-items-center gap-2 text-muted">
                              <Spinner size="sm" color="primary" />
                              <span>Loading audit logs...</span>
                            </div>
                          </td>
                        </tr>
                      )}

                      {!loading && paged.length === 0 && (
                        <tr>
                          <td colSpan={6} className="text-center text-muted py-4">
                            No audit logs found for the selected filters.
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
                      onClick={() => setPage((current) => current - 1)}
                    >
                      <ChevronLeft size={16} />
                    </Button>

                    <Button
                      color="light"
                      className="border"
                      disabled={page >= totalPages}
                      onClick={() => setPage((current) => current + 1)}
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

export default AuditLogsPage;
