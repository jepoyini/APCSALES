import React from "react";
import { ShieldAlert } from "lucide-react";
import { Button, Card, CardBody, Col, Container, Row } from "reactstrap";
import { Link } from "react-router-dom";

type AccessDeniedProps = {
  title?: string;
  message?: string;
};

const AccessDenied: React.FC<AccessDeniedProps> = ({
  title = "Access Denied",
  message = "You do not have permission to open this module.",
}) => {
  return (
    <div className="page-content">
      <Container fluid>
        <Row className="justify-content-center">
          <Col xl={6} lg={8}>
            <Card className="border-0 shadow-sm">
              <CardBody className="text-center py-5">
                <div className="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-4" style={{ width: 72, height: 72 }}>
                  <ShieldAlert size={30} />
                </div>
                <h3 className="mb-2">{title}</h3>
                <p className="text-muted mb-4">{message}</p>
                <Button tag={Link} to="/dashboard" color="primary">
                  Back to Dashboard
                </Button>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default AccessDenied;
