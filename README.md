********************************************************************************
# DAG Switcher

Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au

********************************************************************************
## Summary

Enable project users to switch between any number of Data Access Groups (and/or 
"No assignment"):
 - Adds a table of users/DAGs to the DAGs page so users with DAG permission may
   enable and disable specific DAGs for each project user. 
 - For users with multiple DAGs enabled, adds a display of a user's current DAG 
   assignment in a box at the top of pages where records may be viewed (e.g.
   Dashboard, Export, Import, but not Project Home or Setup, or where a specific
   record is selected: Record Home or an Instrument).
 - Next to the display of current DAG is a button that opens a dialog where the
   user may select an alternative DAG from those that are enabled for them (not
   available when a specific record is selected).
 - Enables user with DAG permission to remove him or herself from a DAG, which 
   is not possible using standard REDCap functionality.

********************************************************************************