package com.vidhi.voters.report;

import java.util.ArrayList;
import java.util.List;

/** A tabular report, already translated, rendered by reports/view.html and exported by TableExporter. */
public class Report {
    private final String title;
    private String subtitle = "";
    private final List<String> headers;
    private final List<List<Object>> rows = new ArrayList<>();
    private List<Object> totals;

    public Report(String title, List<String> headers) {
        this.title = title;
        this.headers = headers;
    }

    public Report subtitle(String s) { this.subtitle = s == null ? "" : s; return this; }
    public void addRow(List<Object> row) { rows.add(row); }
    public void setTotals(List<Object> totals) { this.totals = totals; }

    public String getTitle() { return title; }
    public String getSubtitle() { return subtitle; }
    public List<String> getHeaders() { return headers; }
    public List<List<Object>> getRows() { return rows; }
    public List<Object> getTotals() { return totals; }

    public static boolean isNumber(Object o) { return o instanceof Number; }

    /** Rows shown on screen; exports always contain every row. */
    public static final int DISPLAY_LIMIT = 1000;
    public List<List<Object>> getDisplayRows() { return rows.size() <= DISPLAY_LIMIT ? rows : rows.subList(0, DISPLAY_LIMIT); }
    public boolean isTruncated() { return rows.size() > DISPLAY_LIMIT; }

    /** Display form of a row for templates: formatted text + whether to right-align. */
    public record Cell(String text, boolean number) {}

    public List<Cell> cells(List<Object> row) {
        return row.stream().map(o -> new Cell(TableExporter.fmt(o), o instanceof Number)).toList();
    }
}
