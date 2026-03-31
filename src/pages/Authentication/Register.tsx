import React, { useEffect, useState } from "react";
import {
  Row,
  Col,
  CardBody,
  Card,
  Alert,
  Container,
  Input,
  Label,
  Form,
  FormFeedback,
  Button,
  Spinner,
} from "reactstrap";

import * as Yup from "yup";
import { useFormik } from "formik";
import { useSelector, useDispatch } from "react-redux";
import { Link, useNavigate } from "react-router-dom";
import { createSelector } from "reselect";

import { registerUser, resetRegisterFlag } from "../../slices/thunks";
import ParticlesAuth from "../AuthenticationInner/ParticlesAuth";
import apcLogoFull from "../../assets/images/apc-logo-full.png";

const Register = () => {
  const history = useNavigate();
  const dispatch: any = useDispatch();
  const [loader, setLoader] = useState<boolean>(false);

  const validation = useFormik({
    enableReinitialize: true,
    initialValues: {
      email: "",
      username: "",
      firstname: "",
      lastname: "",
      phone: "",
      password: "",
      confirm_password: "",
      country: "",
      sponsor_id: "",
    },
    validationSchema: Yup.object({
      email: Yup.string().email("Please enter a valid email").required("Please Enter Your Email"),
      username: Yup.string().required("Please Enter Your Username"),
      firstname: Yup.string().required("Please Enter Your Firstname"),
      lastname: Yup.string().required("Please Enter Your Lastname"),
      phone: Yup.string().required("Please Enter Your Phone"),
      country: Yup.string().required("Please Select Your Country"),
      password: Yup.string().required("Please Enter Your Password"),
      confirm_password: Yup.string()
        .oneOf([Yup.ref("password"), ""], "Passwords must match")
        .required("Confirm Password is required"),
    }),
    onSubmit: (values) => {
      dispatch(registerUser(values));
      setLoader(true);
    },
  });

  const selectLayoutState = (state: any) => state.Account;
  const registerData = createSelector(selectLayoutState, (account) => ({
    success: account.success,
    error: account.error,
    registrationError: account.registrationError,
    message: account.message,
  }));

  const { success, error, registrationError, message } = useSelector(registerData);

  const registrationFieldErrors =
    registrationError && typeof registrationError === "object" && !Array.isArray(registrationError)
      ? registrationError
      : null;

  const registrationErrorLines =
    typeof registrationError === "string"
      ? [registrationError]
      : registrationFieldErrors
      ? Object.values(registrationFieldErrors)
      : ["Registration failed"];

  useEffect(() => {
    if (success) {
      setLoader(false);
      setTimeout(() => history("/login"), 2000);
    }
    if (error) {
      setLoader(false);
      if (registrationFieldErrors) {
        validation.setErrors(registrationFieldErrors as Record<string, string>);
      }
    }

    const timer = setTimeout(() => {
      dispatch(resetRegisterFlag());
    }, 3000);

    return () => clearTimeout(timer);
  }, [dispatch, success, error, history, registrationFieldErrors]);

  document.title = "Sign Up | APC Sales Analytics";

  return (
    <React.Fragment>
      <ParticlesAuth>
        <div className="auth-page-content mt-lg-5">
          <Container>
          <Row>
              <Col lg={12}>
                  <div className="text-center mt-sm-5 text-white-50 ">
                      <div>
                          <Link to="/" className="d-inline-block auth-logo">
                              <img src={apcLogoFull} alt="APC Sales Analytics" height="200" />
                          </Link>
                      </div>
                  </div>
              </Col>
          </Row>

            <Row className="justify-content-center">
              <Col md={10} lg={9} xl={8}>
                <Card className="mt-4">
                  <CardBody className="p-4">
                    <div className="text-center mt-2">
                      <h5 className="text-primary">Create New Account</h5>
                    </div>

                    <div className="p-2 mt-4">
                      <Form
                        onSubmit={(e) => {
                          e.preventDefault();
                          validation.handleSubmit();
                          return false;
                        }}
                        className="needs-validation"
                      >
                        {success ? (
                          <Alert color="success">
                            {message || "Registration successful"}
                          </Alert>
                        ) : null}

                        {error ? (
                          <Alert color="danger" className="mb-4">
                            <div style={{ whiteSpace: "pre-line" }}>
                              {registrationErrorLines.join("\n")}
                            </div>
                          </Alert>
                        ) : null}

                        <Row>
                          <Col md={6} className="mb-3">
                            <Label className="form-label">Email <span className="text-danger">*</span></Label>
                            <Input
                              name="email"
                              placeholder="Enter email address"
                              type="email"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.email}
                              invalid={Boolean(validation.touched.email && validation.errors.email)}
                            />
                            {validation.touched.email && validation.errors.email ? (
                              <FormFeedback type="invalid">{validation.errors.email}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Phone <span className="text-danger">*</span></Label>
                            <Input
                              name="phone"
                              placeholder="Enter Phone"
                              type="text"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.phone}
                              invalid={Boolean(validation.touched.phone && validation.errors.phone)}
                            />
                            {validation.touched.phone && validation.errors.phone ? (
                              <FormFeedback type="invalid">{validation.errors.phone}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Username <span className="text-danger">*</span></Label>
                            <Input
                              name="username"
                              placeholder="Enter username"
                              type="text"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.username}
                              invalid={Boolean(validation.touched.username && validation.errors.username)}
                            />
                            {validation.touched.username && validation.errors.username ? (
                              <FormFeedback type="invalid">{validation.errors.username}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Country <span className="text-danger">*</span></Label>
                            <Input
                              type="select"
                              name="country"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.country}
                              invalid={Boolean(validation.touched.country && validation.errors.country)}
                            >
                              <option value="">Select country</option>
                              <option value="PH">Philippines</option>
                              <option value="US">United States</option>
                              <option value="CA">Canada</option>
                            </Input>
                            {validation.touched.country && validation.errors.country ? (
                              <FormFeedback type="invalid">{validation.errors.country}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Firstname <span className="text-danger">*</span></Label>
                            <Input
                              name="firstname"
                              placeholder="Enter firstname"
                              type="text"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.firstname}
                              invalid={Boolean(validation.touched.firstname && validation.errors.firstname)}
                            />
                            {validation.touched.firstname && validation.errors.firstname ? (
                              <FormFeedback type="invalid">{validation.errors.firstname}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Password <span className="text-danger">*</span></Label>
                            <Input
                              name="password"
                              placeholder="Enter Password"
                              type="password"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.password}
                              invalid={Boolean(validation.touched.password && validation.errors.password)}
                            />
                            {validation.touched.password && validation.errors.password ? (
                              <FormFeedback type="invalid">{validation.errors.password}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Lastname <span className="text-danger">*</span></Label>
                            <Input
                              name="lastname"
                              placeholder="Enter Lastname"
                              type="text"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.lastname}
                              invalid={Boolean(validation.touched.lastname && validation.errors.lastname)}
                            />
                            {validation.touched.lastname && validation.errors.lastname ? (
                              <FormFeedback type="invalid">{validation.errors.lastname}</FormFeedback>
                            ) : null}
                          </Col>

                          <Col md={6} className="mb-3">
                            <Label className="form-label">Confirm Password <span className="text-danger">*</span></Label>
                            <Input
                              name="confirm_password"
                              placeholder="Confirm Password"
                              type="password"
                              onChange={validation.handleChange}
                              onBlur={validation.handleBlur}
                              value={validation.values.confirm_password}
                              invalid={Boolean(validation.touched.confirm_password && validation.errors.confirm_password)}
                            />
                            {validation.touched.confirm_password && validation.errors.confirm_password ? (
                              <FormFeedback type="invalid">{validation.errors.confirm_password}</FormFeedback>
                            ) : null}
                          </Col>
                        </Row>

                        <Input type="hidden" name="sponsor_id" value={validation.values.sponsor_id} />

                        <div className="mt-4 text-center">
                          <Button color="success" className="px-5" type="submit" disabled={loader}>
                            {loader ? <Spinner size="sm" className="me-2">Loading...</Spinner> : null}
                            Sign Up
                          </Button>
                        </div>
                      </Form>
                    </div>
                  </CardBody>
                </Card>

                <div className="mt-4 text-center">
                  <p className="mb-0">
                    Already have an account ?{" "}
                    <Link to="/login" className="fw-semibold text-primary text-decoration-underline">
                      Signin
                    </Link>
                  </p>
                </div>
              </Col>
            </Row>
          </Container>
        </div>
      </ParticlesAuth>
    </React.Fragment>
  );
};

export default Register;
