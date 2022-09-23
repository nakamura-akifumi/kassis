package solr

import (
	"fmt"
	"net/url"
	"strconv"
	"strings"
)

type Query struct {
	q        []string
	operator string
	params   url.Values
}

func NewQuery() *Query {
	nq := &Query{operator: "OR"}
	nq.params = make(url.Values)
	nq.params.Set("rows", "10")
	return nq
}

func (query *Query) Q(s string) {
	query.q = append(query.q, s)
}

func (query *Query) Rows(rows int) {
	query.params.Set("rows", strconv.Itoa(rows))
}

func (query *Query) SetParam(k, v string) {
	query.params.Set(k, v)
}

func (query *Query) String() string {
	if len(query.q) > 0 {
		query.params.Set("q", strings.Join(query.q, fmt.Sprintf(" %s ", query.operator)))
	}
	query.params.Set("wt", "json")
	return query.params.Encode()
}
