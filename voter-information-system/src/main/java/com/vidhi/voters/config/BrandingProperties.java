package com.vidhi.voters.config;

import org.springframework.boot.context.properties.ConfigurationProperties;

/**
 * Login page branding (prefix app.branding). To use your own banner, put an image in
 * src/main/resources/static/images/ (or any URL path the app serves) and set banner-image.
 */
@ConfigurationProperties(prefix = "app.branding")
public class BrandingProperties {
    /** Banner illustration on the login page. */
    private String bannerImage = "/images/login-banner.svg";
    /** Optional organisation / campaign name shown on the banner, e.g. "Jan Vikas Party - Jaipur North". */
    private String organisation = "";
    /** Optional small logo shown above the sign-in form. */
    private String logo = "";

    public String getBannerImage() { return bannerImage; }
    public void setBannerImage(String v) { bannerImage = v; }
    public String getOrganisation() { return organisation; }
    public void setOrganisation(String v) { organisation = v; }
    public String getLogo() { return logo; }
    public void setLogo(String v) { logo = v; }
}
