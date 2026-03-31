import React, { useState } from "react";
import axios from "axios";
import { CheckCircle, Globe, RefreshCw } from "lucide-react";
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  Col,
  Container,
  Row,
  Spinner,
} from "reactstrap";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import BreadCrumb from "../../Components/Common/BreadCrumb";
import { usePermissions } from "../../Components/Hooks/UserHooks";
import { APP_PERMISSIONS } from "../../helpers/permissions";

type Site = {
  id: "apc" | "mp" | "pnp";
  name: string;
  domain: string;
  syncUrl: string;
};

const SITES: Site[] = [
  {
    id: "apc",
    name: "American Plaque Co.",
    domain: "americanplaquecompany.com",
    syncUrl: "https://apidb.americanplaquecompany.com/sync_data.php?site=apc",
  },
  {
    id: "mp",
    name: "Master Plaques",
    domain: "masterplaques.com",
    syncUrl: "https://apidb.americanplaquecompany.com/sync_data.php?site=mp",
  },
  {
    id: "pnp",
    name: "Plaques & Patches",
    domain: "plaquesandpatches.com",
    syncUrl: "https://apidb.americanplaquecompany.com/sync_data.php?site=pnp",
  },
];

const SyncDataPage: React.FC = () => {
  document.title = "Sync Data | APC Sales Analytics";
  const { hasPermission } = usePermissions();

  const [syncingSite, setSyncingSite] = useState<Site["id"] | null>(null);
  const [lastSynced, setLastSynced] = useState<Record<Site["id"], string>>({
    apc: "Not synced yet",
    mp: "Not synced yet",
    pnp: "Not synced yet",
  });

  const handleSync = async (site: Site) => {
    if (!hasPermission(APP_PERMISSIONS.integrationsSync)) {
      toast.error("You do not have permission to sync data.");
      return;
    }

    setSyncingSite(site.id);

    try {
      const response = await axios.get(site.syncUrl, {
        headers: {
          "Cache-Control": "no-cache",
        },
      });

      if (response.status < 200 || response.status >= 300) {
        throw new Error(`Sync request failed with status ${response.status}`);
      }

      setLastSynced((current) => ({
        ...current,
        [site.id]: new Date().toLocaleString(),
      }));

      toast.success(`${site.name} sync completed.`);
    } catch (error) {
      console.error(`Failed to sync ${site.name}:`, error);
      toast.error(`Failed to sync ${site.name}.`);
    } finally {
      setSyncingSite(null);
    }
  };

  return (
    <div className="page-content">
      <Container fluid>
        <ToastContainer position="top-right" autoClose={3000} />
        <BreadCrumb title="Sync Data" pageTitle="Dashboard" />

        <Row className="mb-4">
          <Col>
            <h2 className="mb-1">Sync Data</h2>
            <p className="text-muted mb-0">WooCommerce site connections and sync status</p>
          </Col>
        </Row>

        <Row className="g-3">
          {SITES.map((site) => (
            <Col md={4} key={site.id}>
              <Card className="border-0 shadow-sm h-100">
                <CardHeader className="border-0 pb-2">
                  <div className="d-flex align-items-center justify-content-between">
                    <h6 className="mb-0 d-flex align-items-center gap-2">
                      <Globe size={15} /> {site.name}
                    </h6>
                    <Badge color="success" className="d-flex align-items-center gap-1">
                      <CheckCircle size={12} /> Connected
                    </Badge>
                  </div>
                </CardHeader>
                <CardBody>
                  <div className="small">
                    <div className="d-flex justify-content-between mb-2">
                      <span className="text-muted">Domain</span>
                      <code>{site.domain}</code>
                    </div>
                    <div className="d-flex justify-content-between mb-3">
                      <span className="text-muted">Last Synced</span>
                      <span>{lastSynced[site.id]}</span>
                    </div>
                  </div>

                  <Button
                    color="light"
                    className="border w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    onClick={() => handleSync(site)}
                    disabled={
                      syncingSite === site.id ||
                      !hasPermission(APP_PERMISSIONS.integrationsSync)
                    }
                  >
                    {syncingSite === site.id ? (
                      <>
                        <Spinner size="sm" />
                        Syncing...
                      </>
                    ) : (
                      <>
                        <RefreshCw size={13} />
                        Sync Now
                      </>
                    )}
                  </Button>
                </CardBody>
              </Card>
            </Col>
          ))}
        </Row>
      </Container>
    </div>
  );
};

export default SyncDataPage;
