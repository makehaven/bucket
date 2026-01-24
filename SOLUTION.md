# Resolving the 500 Error on STL File Uploads

## Problem

Users are experiencing a "500 AJAX HTTP error" when attempting to upload STL files to the Bucket.

## Cause

The investigation indicates that this is not a bug in the Bucket module's code but a configuration issue on the Drupal server. The Bucket module, when in "blocklist" mode, uses a "permissive extensions" list to override Drupal's default, more restrictive file validation.

The `stl` file extension is likely missing from this "permissive extensions" list in the **live server's configuration**. When a user tries to upload an STL file, Drupal's default validation rejects it, causing the unhandled 500 error during the AJAX upload process.

The module's default configuration *does* include the `stl` extension, which suggests that the configuration on the live server has been changed from the default.

## Solution

A site administrator needs to update the Bucket module's settings in the Drupal admin interface to include the `stl` extension. We also recommend adding `f3d`, another common 3D file format, to prevent future issues.

### Instructions for Site Administrators

1.  Log in to your Drupal site as an administrator.
2.  Navigate to the Bucket settings page. The typical path is **Configuration > Media > Bucket settings**.
3.  Locate the text area labeled **"Permissive extensions used in blocklist mode"**.
4.  Add `stl` and `f3d` to the list of extensions. The extensions should be space-separated.
5.  Scroll to the bottom of the page and click the **"Save configuration"** button.

After saving the new configuration, users should be able to upload STL and F3D files without encountering the 500 error.
