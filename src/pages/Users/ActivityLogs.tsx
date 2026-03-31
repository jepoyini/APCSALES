// ================================================================
// FILE: src/pages/Users/ActivityLogs.jsx
// ================================================================
import React, { useState } from "react";
import {
  Table,
  Spinner,
  Pagination,
  PaginationItem,
  PaginationLink,
  Row,
  Col,
  Input,
  Label,
} from "reactstrap";

const ActivityLogs = ({ logs, loading }) => {
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);

  const effectivePageSize = pageSize === "ALL" ? logs.length : pageSize;
  const total = logs.length;
  const totalPages = Math.ceil(total / effectivePageSize);
  const start = (page - 1) * effectivePageSize;
  const end = start + effectivePageSize;
  const currentLogs = logs.slice(start, end);

  const handlePageChange = (newPage) => {
    if (newPage >= 1 && newPage <= totalPages) {
      setPage(newPage);
    }
  };

  const handlePageSizeChange = (e) => {
    const val = e.target.value === "ALL" ? "ALL" : parseInt(e.target.value, 10);
    setPageSize(val);
    setPage(1);
  };

  const renderPageNumbers = () => {
    const pages = [];
    const maxVisible = 5;
    let startPage = Math.max(1, page - 2);
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);

    if (endPage - startPage < maxVisible - 1) {
      startPage = Math.max(1, endPage - maxVisible + 1);
    }

    if (startPage > 1) {
      pages.push(
        <PaginationItem key="first">
          <PaginationLink onClick={() => handlePageChange(1)}>1</PaginationLink>
        </PaginationItem>
      );
      if (startPage > 2) {
        pages.push(
          <PaginationItem key="ellipsis1" disabled>
            <PaginationLink>…</PaginationLink>
          </PaginationItem>
        );
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      pages.push(
        <PaginationItem active={page === i} key={i}>
          <PaginationLink onClick={() => handlePageChange(i)}>{i}</PaginationLink>
        </PaginationItem>
      );
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        pages.push(
          <PaginationItem key="ellipsis2" disabled>
            <PaginationLink>…</PaginationLink>
          </PaginationItem>
        );
      }
      pages.push(
        <PaginationItem key="last">
          <PaginationLink onClick={() => handlePageChange(totalPages)}>
            {totalPages}
          </PaginationLink>
        </PaginationItem>
      );
    }

    return pages;
  };

  if (loading) {
    return (
      <div
        className="d-flex justify-content-center align-items-center"
        style={{ minHeight: "200px" }}
      >
        <Spinner color="primary" />
      </div>
    );
  }

  if (!logs || logs.length === 0) {
    return <p className="text-center text-muted">No activity logs found.</p>;
  }

  return (
    <>
      {/* Scrollable Logs Table (vertical + horizontal) */}
      <div style={{ maxHeight: "350px", overflowY: "auto", overflowX: "auto" }}>
<Table
  responsive
  bordered
  hover
  size="sm"
  className="align-middle table-sm"
  style={{ minWidth: "800px" }} // ensures horizontal scroll for small screens
>
  <thead className="table-light small">
    <tr>
      <th style={{ minWidth: "150px" }}>Date</th>
      <th style={{ minWidth: "120px" }}>IP</th>
      <th style={{ minWidth: "200px" }}>Location</th>
      <th style={{ minWidth: "150px" }}>Activity</th>
      <th>Data</th> {/* Flexible column */}
    </tr>
  </thead>
  <tbody className="small">
    {currentLogs.map((log, i) => (
      <tr key={i}>
        <td>{log.date_created}</td>
        <td>{log.ip_address}</td>
        <td>{log.ip_location}</td>
        <td>{log.type}</td>
        <td
          style={{
            whiteSpace: "nowrap",
            overflow: "hidden",
            textOverflow: "ellipsis",
          }}
          title={log.data} // full text on hover
        >
          {log.data || "—"}
        </td>
      </tr>
    ))}
  </tbody>
</Table>

      </div>

      {/* Pagination + Page Size Dropdown */}
      <Row className="align-items-center mt-2">
        {/* Page size dropdown */}
        <Col xs="12" md="4" className="mb-2 mb-md-0 d-flex align-items-center">
          <Label for="pageSizeSelect" className="me-2 mb-0 text-muted small">
            Rows per page:
          </Label>
          <Input
            id="pageSizeSelect"
            type="select"
            value={pageSize}
            onChange={handlePageSizeChange}
            bsSize="sm"
            style={{ maxWidth: "90px" }}
          >
            {[10, 20, 50, 100].map((size) => (
              <option key={size} value={size}>
                {size}
              </option>
            ))}
            <option value="ALL">ALL</option>
          </Input>
        </Col>

        {/* Showing info */}
        <Col xs="12" md="4" className="text-md-center mb-2 mb-md-0">
          <small className="text-muted">
            Showing <strong>{start + 1}</strong> –{" "}
            <strong>{Math.min(end, total)}</strong> of{" "}
            <strong>{total}</strong>
          </small>
        </Col>

        {/* Pagination */}
        <Col
          xs="12"
          md="4"
          className="d-flex justify-content-md-end justify-content-center"
        >
          <Pagination
            size="sm"
            className="mb-0 flex-wrap justify-content-end"
            style={{ maxWidth: "100%" }}
          >
            <PaginationItem disabled={page === 1}>
              <PaginationLink previous onClick={() => handlePageChange(page - 1)} />
            </PaginationItem>

            {renderPageNumbers()}

            <PaginationItem disabled={page === totalPages}>
              <PaginationLink next onClick={() => handlePageChange(page + 1)} />
            </PaginationItem>
          </Pagination>
        </Col>
      </Row>
    </>
  );
};

export default ActivityLogs;
