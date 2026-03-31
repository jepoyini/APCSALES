import React, { useEffect, useMemo, useState } from "react";
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
  Row,
  Spinner,
  Badge,
} from "reactstrap";
import {
  ChevronLeft,
  ChevronRight,
  Search,
  Package,
  DollarSign,
  ShoppingCart,
  TrendingUp,
} from "lucide-react";
import BreadCrumb from "../../Components/Common/BreadCrumb";

type SiteId = "apc" | "mp" | "pnp";
type SortKey =
  | "revenue_desc"
  | "revenue_asc"
  | "units_desc"
  | "units_asc"
  | "aov_desc"
  | "aov_asc"
  | "name_asc"
  | "name_desc";

type TopProduct = {
  id: string;
  name: string;
  sku: string;
  site_id: SiteId;
  revenue: number;
  units_sold: number;
  aov_contribution: number;
};

const COLORS = [
  "hsl(222,62%,22%)",
  "hsl(36,95%,52%)",
  "hsl(142,71%,45%)",
  "hsl(217,91%,60%)",
];

const SITE_OPTIONS: Array<{ id: SiteId; name: string }> = [
  { id: "apc", name: "American Plaque Co." },
  { id: "mp", name: "Master Plaques" },
  { id: "pnp", name: "Plaques & Patches" },
];

const SITE_COLORS: Record<SiteId, string> = {
  apc: "primary",   // blue
  mp: "success",    // green
  pnp: "warning",   // yellow
};

const ProductsPage: React.FC = () => {
  document.title = "Products | APC Sales Analytics";

  const [products, setProducts] = useState<TopProduct[]>([]);
  const [loading, setLoading] = useState(true);

  const [search, setSearch] = useState("");
  const [siteFilter, setSiteFilter] = useState("all");
  const [sortBy, setSortBy] = useState<SortKey>("revenue_desc");
  const [rowsPerPage, setRowsPerPage] = useState(10);

  const [page, setPage] = useState(1);

  useEffect(() => {
    fetch("https://apidb.americanplaquecompany.com/analytics/products")
      .then((res) => res.json())
      .then((data) => {
        if (data.status === "success") {
          const rows = Array.isArray(data.products)
            ? data.products
            : Array.isArray(data.data)
            ? data.data
            : [];

          const formatted: TopProduct[] = rows.map((p: any, index: number) => ({
            id: String(p.id ?? p.product_id ?? index),
            name: String(p.product_name ?? p.name ?? "Unnamed Product"),
            sku: String(p.sku ?? ""),
            site_id: String(p.site ?? p.site_id ?? "apc").toLowerCase() as SiteId,
            revenue: Number(p.revenue ?? 0),
            units_sold: Number(p.units_sold ?? 0),
            aov_contribution: Number(p.aov_contribution ?? 0),
          }));

          setProducts(formatted);
        } else {
          setProducts([]);
        }
      })
      .catch((err) => {
        console.error("Products fetch error:", err);
        setProducts([]);
      })
      .finally(() => setLoading(false));
  }, []);

  const filteredProducts = useMemo(() => {
    return products.filter((p) => {
      const keyword = search.trim().toLowerCase();

      if (
        keyword &&
        !p.name.toLowerCase().includes(keyword) &&
        !p.sku.toLowerCase().includes(keyword)
      ) {
        return false;
      }

      if (siteFilter !== "all" && p.site_id !== siteFilter) {
        return false;
      }

      return true;
    });
  }, [products, search, siteFilter]);

  const sortedProducts = useMemo(() => {
    const rows = [...filteredProducts];

    switch (sortBy) {
      case "revenue_desc":
        rows.sort((a, b) => b.revenue - a.revenue);
        break;
      case "revenue_asc":
        rows.sort((a, b) => a.revenue - b.revenue);
        break;
      case "units_desc":
        rows.sort((a, b) => b.units_sold - a.units_sold);
        break;
      case "units_asc":
        rows.sort((a, b) => a.units_sold - b.units_sold);
        break;
      case "aov_desc":
        rows.sort((a, b) => b.aov_contribution - a.aov_contribution);
        break;
      case "aov_asc":
        rows.sort((a, b) => a.aov_contribution - b.aov_contribution);
        break;
      case "name_desc":
        rows.sort((a, b) => b.name.localeCompare(a.name));
        break;
      case "name_asc":
      default:
        rows.sort((a, b) => a.name.localeCompare(b.name));
        break;
    }

    return rows;
  }, [filteredProducts, sortBy]);

  useEffect(() => {
    setPage(1);
  }, [search, siteFilter, sortBy, rowsPerPage]);

  const kpis = useMemo(() => {
    const totalProducts = filteredProducts.length;
    const totalRevenue = filteredProducts.reduce((sum, p) => sum + Number(p.revenue || 0), 0);
    const totalUnitsSold = filteredProducts.reduce((sum, p) => sum + Number(p.units_sold || 0), 0);
    const avgAovContribution =
      totalProducts > 0
        ? filteredProducts.reduce((sum, p) => sum + Number(p.aov_contribution || 0), 0) /
          totalProducts
        : 0;

    return {
      totalProducts,
      totalRevenue,
      totalUnitsSold,
      avgAovContribution,
    };
  }, [filteredProducts]);

  const topByRevenue = useMemo(() => {
    return [...filteredProducts]
      .sort((a, b) => b.revenue - a.revenue)
      .slice(0, 8);
  }, [filteredProducts]);

  const totalPages = Math.max(1, Math.ceil(sortedProducts.length / rowsPerPage));

  const pagedProducts = useMemo(() => {
    const start = (page - 1) * rowsPerPage;
    return sortedProducts.slice(start, start + rowsPerPage);
  }, [sortedProducts, page, rowsPerPage]);

  const startRow = sortedProducts.length === 0 ? 0 : (page - 1) * rowsPerPage + 1;
  const endRow = Math.min(page * rowsPerPage, sortedProducts.length);

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Products" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col>
            <h2 className="mb-1">Product Performance</h2>
            <p className="text-muted mb-0">
              Identify productive vs non-productive products
            </p>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          <Col xl={3} md={6}>
            <Card className="border-0 shadow-sm h-100">
              <CardBody>
                <div className="d-flex justify-content-between align-items-start mb-3">
                  <div
                    className="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center"
                    style={{ width: 38, height: 38 }}
                  >
                    <Package className="text-primary" size={16} />
                  </div>
	                </div>
	                <h3 className="mb-1">
                    {loading ? (
                      <Spinner size="sm" color="primary" />
                    ) : (
                      kpis.totalProducts.toLocaleString()
                    )}
                  </h3>
	                <p className="text-muted mb-0">Total Products</p>
	              </CardBody>
            </Card>
          </Col>

          <Col xl={3} md={6}>
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
          </Col>

          <Col xl={3} md={6}>
            <Card className="border-0 shadow-sm h-100">
              <CardBody>
                <div className="d-flex justify-content-between align-items-start mb-3">
                  <div
                    className="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center"
                    style={{ width: 38, height: 38 }}
                  >
                    <ShoppingCart className="text-warning" size={16} />
                  </div>
                </div>
	                <h3 className="mb-1">
                    {loading ? (
                      <Spinner size="sm" color="primary" />
                    ) : (
                      kpis.totalUnitsSold.toLocaleString()
                    )}
                  </h3>
	                <p className="text-muted mb-0">Total Units Sold</p>
	              </CardBody>
            </Card>
          </Col>

          <Col xl={3} md={6}>
            <Card className="border-0 shadow-sm h-100">
              <CardBody>
                <div className="d-flex justify-content-between align-items-start mb-3">
                  <div
                    className="rounded-circle bg-info-subtle d-flex align-items-center justify-content-center"
                    style={{ width: 38, height: 38 }}
                  >
                    <TrendingUp className="text-info" size={16} />
                  </div>
                </div>
	                <h3 className="mb-1">
                    {loading ? (
                      <Spinner size="sm" color="primary" />
                    ) : (
                      <>
                        $
                        {kpis.avgAovContribution.toLocaleString(undefined, {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        })}
                      </>
                    )}
                  </h3>
	                <p className="text-muted mb-0">Avg AOV Contribution</p>
	              </CardBody>
            </Card>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          <Col lg={4}>
            <Label className="form-label">Search</Label>
            <InputGroup>
              <InputGroupText>
                <Search size={16} />
              </InputGroupText>
              <Input
                placeholder="Search products or SKU..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </InputGroup>
          </Col>

          <Col lg={4}>
            <Label className="form-label">Site</Label>
            <Input
              type="select"
              value={siteFilter}
              onChange={(e) => setSiteFilter(e.target.value)}
            >
              <option value="all">All Sites</option>
              {SITE_OPTIONS.map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </Input>
          </Col>

          <Col lg={4}>
            <Label className="form-label">Sort</Label>
            <Input
              type="select"
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as SortKey)}
            >
              <option value="revenue_desc">Revenue: High to Low</option>
              <option value="revenue_asc">Revenue: Low to High</option>
              <option value="units_desc">Units Sold: High to Low</option>
              <option value="units_asc">Units Sold: Low to High</option>
              <option value="aov_desc">AOV: High to Low</option>
              <option value="aov_asc">AOV: Low to High</option>
              <option value="name_asc">Name: A to Z</option>
              <option value="name_desc">Name: Z to A</option>
            </Input>
          </Col>
        </Row>

        <Row className="mb-4">
          <Col>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0">Top Products by Revenue</h6>
              </CardHeader>

	              <CardBody style={{ height: 320 }}>
	                {loading ? (
	                  <div className="text-center py-5">
                      <div className="d-inline-flex align-items-center gap-2 text-muted">
                        <Spinner size="sm" color="primary" />
                        <span>Loading products...</span>
                      </div>
                    </div>
	                ) : topByRevenue.length === 0 ? (
	                  <div className="text-center py-5 text-muted">
	                    No products found for the selected filters.
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={topByRevenue} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                      <XAxis
                        type="number"
                        tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
                        tick={{ fontSize: 11 }}
                      />
                      <YAxis
                        type="category"
                        dataKey="name"
                        tick={{ fontSize: 10 }}
                        width={190}
                      />
                      <Tooltip
                        formatter={(v: number) => [`$${v.toLocaleString()}`, "Revenue"]}
                      />
                      <Bar dataKey="revenue" radius={[0, 4, 4, 0]}>
                        {topByRevenue.map((_, i) => (
                          <Cell key={i} fill={COLORS[i % COLORS.length]} />
                        ))}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                )}
              </CardBody>
            </Card>
          </Col>
        </Row>

        <Row>
          <Col>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0">
                <h6 className="mb-0">Full Product Catalog Performance</h6>
              </CardHeader>

	              <CardBody>
	                {loading ? (
	                  <div className="text-center py-5">
                      <div className="d-inline-flex align-items-center gap-2 text-muted">
                        <Spinner size="sm" color="primary" />
                        <span>Loading products...</span>
                      </div>
                    </div>
	                ) : (
	                  <>
                    <div className="table-responsive">
                      <table className="table table-hover align-middle mb-0">
                        <thead>
                          <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Site</th>
                            <th>Revenue</th>
                            <th>Units Sold</th>
                            <th>AOV Contribution</th>
                          </tr>
                        </thead>

                        <tbody>
                          {pagedProducts.map((p) => (
                            <tr key={p.id}>
                              <td className="fw-medium">{p.name}</td>
                              <td>
                                <code>{p.sku}</code>
                              </td>
                              <td>
                                <Badge
                                  color={SITE_COLORS[p.site_id] || "secondary"}
                                  className="text-uppercase"
                                >
                                  {p.site_id}
                                </Badge>
                              </td>
                              <td>
                                $
                                {p.revenue.toLocaleString(undefined, {
                                  minimumFractionDigits: 2,
                                  maximumFractionDigits: 2,
                                })}
                              </td>
                              <td>{p.units_sold.toLocaleString()}</td>
                              <td>
                                $
                                {p.aov_contribution.toLocaleString(undefined, {
                                  minimumFractionDigits: 2,
                                  maximumFractionDigits: 2,
                                })}
                              </td>
                            </tr>
                          ))}

                          {pagedProducts.length === 0 && (
                            <tr>
                              <td colSpan={6} className="text-center text-muted py-4">
                                No products found for the selected filters.
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>

                    <div className="d-flex flex-wrap justify-content-between align-items-center mt-4 pt-3 border-top gap-3">

                    {/* LEFT: Rows per page */}
                    <div className="d-flex align-items-center gap-2">
                      <small className="text-muted">Rows per page</small>
                      <Input
                        type="select"
                        value={rowsPerPage}
                        onChange={(e) => setRowsPerPage(Number(e.target.value))}
                        style={{ width: 80 }}
                      >
                        <option value={10}>10</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                        <option value={100}>100</option>
                      </Input>
                    </div>

                    {/* MIDDLE: Showing count */}
                    <div className="text-center flex-grow-1">
                      <small className="text-muted">
                        Showing {startRow} - {endRow} of {sortedProducts.length}
                      </small>
                    </div>

                    {/* RIGHT: Pagination */}
                    <div className="d-flex align-items-center gap-2">
                      <Button
                        color="light"
                        className="border"
                        disabled={page <= 1}
                        onClick={() => setPage((p) => p - 1)}
                      >
                        <ChevronLeft size={16} />
                      </Button>

                      <small className="text-muted">
                        Page {page} of {totalPages}
                      </small>

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
                  </>
                )}
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default ProductsPage;
