import React, { useEffect, useMemo, useState, useCallback } from "react";
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  RefreshCcw,
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
  user_id?: number | null;
  user_name: string;
  action: string;
  details: string;
  ip_address: string;
  ip_location?: string;
  created_at: string;
};

type SortKey =
  | "created_at"
  | "user_name"
  | "action"
  | "details"
  | "ip_address";

type SortDirection = "asc" | "desc";

const API_URL = "https://apidb.americanplaquecompany.com/analytics/auditlogs";

const AuditLogsPage: React.FC = () => {
  document.title = "Audit Logs | APC Sales Analytics";

  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [actionFilter, setActionFilter] = useState("all");
  const [userFilter, setUserFilter] = useState("all");
  const [sortKey, setSortKey] = useState<SortKey>("created_at");
  const [sortDirection, setSortDirection] = useState<SortDirection>("desc");
  const [page, setPage] = useState(1);
  const perPage = 10;

  const fetchAuditLogs = useCallback(async (isRefresh = false) => {
    try {
      setError("");

      if (isRefresh) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      const response = await fetch(API_URL, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
        },
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const data = await response.json();

      const rows = Array.isArray(data?.logs)
        ? data.logs
        : Array.isArray(data?.auditlogs)
        ? data.auditlogs
        : Array.isArray(data?.data)
        ? data.data
        : Array.isArray(data)
        ? data
        : [];

      const mapped: AuditLog[] = rows.map((log: any, index: number) => ({
        id: String(log.id ?? index),
        user_id: log.user_id ?? null,
        user_name: String(log.user_name ?? "System"),
        action: String(log.action ?? log.type ?? "unknown"),
        details: String(log.details ?? log.data ?? ""),
        ip_address: String(log.ip_address ?? "-"),
        ip_location: String(log.ip_location ?? ""),
        created_at: String(log.created_at ?? log.date_created ?? ""),
      }));

      setLogs(mapped);
    } catch (err: any) {
      console.error("Error fetching audit logs:", err);
      setLogs([]);
      setError(err?.message || "Failed to load audit logs.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchAuditLogs();
  }, [fetchAuditLogs]);

  const actionOptions = useMemo(() => {
    return Array.from(new Set(logs.map((log) => log.action).filter(Boolean))).sort((a, b) =>
      a.localeCompare(b)
    );
  }, [logs]);

  const userOptions = useMemo(() => {
    return Array.from(new Set(logs.map((log) => log.user_name).filter(Boolean))).sort((a, b) =>
      a.localeCompare(b)
    );
  }, [logs]);

  const filtered = useMemo(() => {
    const keyword = search.trim().toLowerCase();

    return logs.filter((log) => {
      if (
        keyword &&
        !log.user_name.toLowerCase().includes(keyword) &&
        !log.action.toLowerCase().includes(keyword) &&
        !log.details.toLowerCase().includes(keyword) &&
        !log.ip_address.toLowerCase().includes(keyword) &&
        !(log.ip_location || "").toLowerCase().includes(keyword)
      ) {
        return false;
      }

      if (actionFilter !== "all" && log.action !== actionFilter) {
        return false;
      }

      if (userFilter !== "all" && log.user_name !== userFilter) {
        return false;
      }

      return true;
    });
  }, [search, actionFilter, userFilter, logs]);

  const sorted = useMemo(() => {
    const getValue = (log: AuditLog) => {
      switch (sortKey) {
        case "created_at":
          return log.created_at ? new Date(log.created_at).getTime() : 0;
        case "user_name":
          return log.user_name.toLowerCase();
        case "action":
          return log.action.toLowerCase();
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

      if (aValue < bValue) return sortDirection === "asc" ? -1 : 1;
      if (aValue > bValue) return sortDirection === "asc" ? 1 : -1;
      return 0;
    });
  }, [filtered, sortDirection, sortKey]);

  useEffect(() => {
    setPage(1);
  }, [search, actionFilter, userFilter, sortKey, sortDirection]);

  const totalPages = Math.max(1, Math.ceil(sorted.length / perPage));

  useEffect(() => {
    if (page > totalPages) {
      setPage(totalPages);
    }
  }, [page, totalPages]);

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

  const getActionColor = (action: string) => {
    const normalized = action.toLowerCase();

    if (normalized.includes("login_failed") || normalized.includes("failed")) return "danger";
    if (normalized.includes("delete") || normalized.includes("remove")) return "danger";
    if (normalized.includes("login")) return "success";
    if (normalized.includes("logout")) return "dark";
    if (normalized.includes("create") || normalized.includes("add")) return "info";
    if (normalized.includes("update") || normalized.includes("edit")) return "warning";
    if (normalized.includes("sync") || normalized.includes("import")) return "primary";
    if (normalized.includes("role")) return "secondary";

    return "secondary";
  };

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Audit Logs" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col md={8}>
            <h2 className="mb-1">Audit Logs</h2>
            {loading ? (
              <p className="text-muted mb-0 d-inline-flex align-items-center gap-2">
                <Spinner size="sm" color="primary" />
                <span>Loading audit logs...</span>
              </p>
            ) : error ? (
              <p className="text-danger mb-0">{error}</p>
            ) : (
              <p className="text-muted mb-0">{filtered.length} log entries found</p>
            )}
          </Col>

          <Col md={4} className="text-md-end mt-3 mt-md-0">
            <Button
              color="light"
              className="border"
              onClick={() => fetchAuditLogs(true)}
              disabled={loading || refreshing}
            >
              {refreshing ? (
                <>
                  <Spinner size="sm" className="me-2" />
                  Refreshing...
                </>
              ) : (
                <>
                  <RefreshCcw size={16} className="me-2" />
                  Refresh
                </>
              )}
            </Button>
          </Col>
        </Row>

        <Row>
          <Col>
            <Card className="border-0 shadow-sm">
              <CardBody>
                <Row className="g-3 mb-4">
                  <Col md={4}>
                    <Label className="form-label">Search</Label>
                    <InputGroup>
                      <InputGroupText>
                        <Search size={16} />
                      </InputGroupText>
                      <Input
                        type="text"
                        placeholder="Search audit logs..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        disabled={loading}
                      />
                    </InputGroup>
                  </Col>

                  <Col md={4}>
                    <Label className="form-label">Action</Label>
                    <Input
                      type="select"
                      value={actionFilter}
                      onChange={(e) => setActionFilter(e.target.value)}
                      disabled={loading}
                    >
                      <option value="all">All Actions</option>
                      {actionOptions.map((action) => (
                        <option key={action} value={action}>
                          {action.charAt(0).toUpperCase() + action.slice(1)}
                        </option>
                      ))}
                    </Input>
                  </Col>

                  <Col md={4}>
                    <Label className="form-label">User</Label>
                    <Input
                      type="select"
                      value={userFilter}
                      onChange={(e) => setUserFilter(e.target.value)}
                      disabled={loading}
                    >
                      <option value="all">All Users</option>
                      {userOptions.map((user) => (
                        <option key={user} value={user}>
                          {user}
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
                      {loading && (
                        <tr>
                          <td colSpan={5} className="text-center py-5">
                            <div className="d-inline-flex align-items-center gap-2 text-muted">
                              <Spinner color="primary" />
                              <span>Loading audit logs...</span>
                            </div>
                          </td>
                        </tr>
                      )}

                      {!loading &&
                        !error &&
                        paged.map((log) => (
                          <tr key={log.id}>
                            <td>
                              <code>
                                {log.created_at
                                  ? new Date(log.created_at).toLocaleString()
                                  : "-"}
                              </code>
                            </td>
                            <td>
                              <div className="fw-medium">{log.user_name}</div>
                              {log.user_id ? (
                                <small className="text-muted">ID: {log.user_id}</small>
                              ) : null}
                            </td>
                            <td>
                              <Badge color={getActionColor(log.action)}>
                                {log.action.charAt(0).toUpperCase() + log.action.slice(1)}
                              </Badge>
                            </td>
                            <td className="text-muted" style={{ minWidth: "320px" }}>
                              {log.details || "-"}
                              {log.ip_location ? (
                                <div className="small text-muted mt-1">
                                  Location: {log.ip_location}
                                </div>
                              ) : null}
                            </td>
                            <td>
                              <code>{log.ip_address}</code>
                            </td>
                          </tr>
                        ))}

                      {!loading && error && (
                        <tr>
                          <td colSpan={5} className="text-center py-5">
                            <div className="text-danger mb-3">{error}</div>
                            <Button color="primary" onClick={() => fetchAuditLogs()}>
                              Try Again
                            </Button>
                          </td>
                        </tr>
                      )}

                      {!loading && !error && paged.length === 0 && (
                        <tr>
                          <td colSpan={5} className="text-center text-muted py-4">
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