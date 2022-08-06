package kassiscore

import (
	"bufio"
	"bytes"
	"context"
	"encoding/xml"
	"errors"
	"fmt"
	"github.com/PuerkitoBio/goquery"
	"github.com/google/go-tika/tika"
	"github.com/rs/zerolog/log"
	"github.com/tcnksm/go-httpstat"
	"github.com/vanng822/go-solr/solr"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

type Material struct {
	ID         string   `json:"id"`
	MaterialID string   `json:"materialid"`
	ObjectType string   `json:"objecttype"`
	Foldername string   `json:"foldername"`
	Filename   string   `json:"filename"`
	Sheetname  string   `json:"sheetname"`
	Mediatype  string   `json:"mediatype"`
	Contents   []string `json:"contents"`
	Title      string   `json:"title"`
}

// KWQIF は、Web用のレスポンス構造体
type KWQIF struct {
	QueryString  string
	QueryMessage string
	NumOfPage    int
	Curretpage   int
	Lastpage     int
}
type KWRIF struct {
	NumFound        int
	ResponseStatus  string
	ResponseMessage string
	KQ              KWQIF
	Materials       []Material
}

// SRU DCNDL
type SearchRetrieveResponse struct {
	XMLName            xml.Name `xml:"searchRetrieveResponse"`
	Text               string   `xml:",chardata"`
	Xmlns              string   `xml:"xmlns,attr"`
	Version            string   `xml:"version"`
	NumberOfRecords    string   `xml:"numberOfRecords"`
	NextRecordPosition string   `xml:"nextRecordPosition"`
	ExtraResponseData  struct {
		Text   string `xml:",chardata"`
		Facets struct {
			Text string `xml:",chardata"`
			Lst  []struct {
				Text string `xml:",chardata"`
				Name string `xml:"name,attr"`
				Int  []struct {
					Text string `xml:",chardata"`
					Name string `xml:"name,attr"`
				} `xml:"int"`
			} `xml:"lst"`
		} `xml:"facets"`
	} `xml:"extraResponseData"`
	Records struct {
		Text   string `xml:",chardata"`
		Record []struct {
			Text          string `xml:",chardata"`
			RecordSchema  string `xml:"recordSchema"`
			RecordPacking string `xml:"recordPacking"`
			RecordData    struct {
				Text string `xml:",chardata"`
				Dc   struct {
					Text           string   `xml:",chardata"`
					Dc             string   `xml:"dc,attr"`
					SrwDc          string   `xml:"srw_dc,attr"`
					Xsi            string   `xml:"xsi,attr"`
					SchemaLocation string   `xml:"schemaLocation,attr"`
					Title          string   `xml:"title"`
					Creator        string   `xml:"creator"`
					Subject        []string `xml:"subject"`
					Publisher      string   `xml:"publisher"`
					Language       string   `xml:"language"`
				} `xml:"dc"`
			} `xml:"recordData"`
			RecordPosition string `xml:"recordPosition"`
		} `xml:"record"`
	} `xml:"records"`
}

// DCNDL
type DCNDLOAIPMH struct {
	XMLName     xml.Name `xml:"OAI-PMH"`
	Text        string   `xml:",chardata"`
	Xmlns       string   `xml:"xmlns,attr"`
	ListRecords struct {
		Text   string `xml:",chardata"`
		Record []struct {
			Text   string `xml:",chardata"`
			Header struct {
				Text       string `xml:",chardata"`
				Identifier string `xml:"identifier"`
				Datestamp  string `xml:"datestamp"`
			} `xml:"header"`
			Metadata struct {
				Text string `xml:",chardata"`
				RDF  struct {
					Text             string `xml:",chardata"`
					Dcterms          string `xml:"dcterms,attr"`
					Rdf              string `xml:"rdf,attr"`
					Dcndl            string `xml:"dcndl,attr"`
					Foaf             string `xml:"foaf,attr"`
					Rdfs             string `xml:"rdfs,attr"`
					Dc               string `xml:"dc,attr"`
					Owl              string `xml:"owl,attr"`
					BibAdminResource struct {
						Text                 string `xml:",chardata"`
						About                string `xml:"about,attr"`
						CatalogingStatus     string `xml:"catalogingStatus"`
						CatalogingRule       string `xml:"catalogingRule"`
						BibRecordCategory    string `xml:"bibRecordCategory"`
						BibRecordSubCategory string `xml:"bibRecordSubCategory"`
						Record               struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
						} `xml:"record"`
						Description string `xml:"description"`
					} `xml:"BibAdminResource"`
					BibResource []struct {
						Text    string `xml:",chardata"`
						About   string `xml:"about,attr"`
						SeeAlso []struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
						} `xml:"seeAlso"`
						Identifier []struct {
							Text     string `xml:",chardata"`
							Datatype string `xml:"datatype,attr"`
						} `xml:"identifier"`
						Title struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text          string `xml:",chardata"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"title"`
						Volume struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text          string `xml:",chardata"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"volume"`
						SeriesTitle []struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text          string `xml:",chardata"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"seriesTitle"`
						Creator []struct {
							Text  string `xml:",chardata"`
							Agent struct {
								Text          string `xml:",chardata"`
								About         string `xml:"about,attr"`
								Name          string `xml:"name"`
								Transcription string `xml:"transcription"`
							} `xml:"Agent"`
						} `xml:"creator"`
						Publisher []struct {
							Text  string `xml:",chardata"`
							Agent struct {
								Text          string `xml:",chardata"`
								Name          string `xml:"name"`
								Transcription string `xml:"transcription"`
								Location      string `xml:"location"`
								Description   string `xml:"description"`
							} `xml:"Agent"`
						} `xml:"publisher"`
						PublicationPlace struct {
							Text     string `xml:",chardata"`
							Datatype string `xml:"datatype,attr"`
						} `xml:"publicationPlace"`
						Date   string `xml:"date"`
						Issued []struct {
							Text     string `xml:",chardata"`
							Datatype string `xml:"datatype,attr"`
						} `xml:"issued"`
						Subject struct {
							Text        string `xml:",chardata"`
							Datatype    string `xml:"datatype,attr"`
							Resource    string `xml:"resource,attr"`
							Description struct {
								Text          string `xml:",chardata"`
								About         string `xml:"about,attr"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"subject"`
						Language []struct {
							Text     string `xml:",chardata"`
							Datatype string `xml:"datatype,attr"`
						} `xml:"language"`
						Extent       string `xml:"extent"`
						MaterialType []struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
							Label    string `xml:"label,attr"`
						} `xml:"materialType"`
						AccessRights string `xml:"accessRights"`
						Audience     string `xml:"audience"`
						Record       struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
						} `xml:"record"`
						PartInformation []struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text    string `xml:",chardata"`
								Title   string `xml:"title"`
								Creator string `xml:"creator"`
							} `xml:"Description"`
						} `xml:"partInformation"`
						Alternative struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text          string `xml:",chardata"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"alternative"`
						Description []string `xml:"description"`
						Price       string   `xml:"price"`
						Edition     string   `xml:"edition"`
						Genre       []struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text  string `xml:",chardata"`
								About string `xml:"about,attr"`
								Value string `xml:"value"`
							} `xml:"Description"`
						} `xml:"genre"`
						SeriesCreator string `xml:"seriesCreator"`
						UniformTitle  struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text          string `xml:",chardata"`
								About         string `xml:"about,attr"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"uniformTitle"`
						PublicationPeriodicity string `xml:"publicationPeriodicity"`
						PublicationStatus      string `xml:"publicationStatus"`
						VolumeTitle            struct {
							Text        string `xml:",chardata"`
							Description struct {
								Text          string `xml:",chardata"`
								Value         string `xml:"value"`
								Transcription string `xml:"transcription"`
							} `xml:"Description"`
						} `xml:"volumeTitle"`
						VolumeRange string `xml:"volumeRange"`
						Relation    []struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
							Label    string `xml:"label,attr"`
						} `xml:"relation"`
						Replaces struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
							Label    string `xml:"label,attr"`
						} `xml:"replaces"`
						IsReplacedBy struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
							Label    string `xml:"label,attr"`
						} `xml:"isReplacedBy"`
						OriginalLanguage struct {
							Text     string `xml:",chardata"`
							Datatype string `xml:"datatype,attr"`
						} `xml:"originalLanguage"`
					} `xml:"BibResource"`
					Item struct {
						Text         string `xml:",chardata"`
						About        string `xml:"about,attr"`
						HoldingAgent struct {
							Text  string `xml:",chardata"`
							Agent struct {
								Text       string `xml:",chardata"`
								Name       string `xml:"name"`
								Identifier struct {
									Text     string `xml:",chardata"`
									Datatype string `xml:"datatype,attr"`
								} `xml:"identifier"`
							} `xml:"Agent"`
						} `xml:"holdingAgent"`
						SeeAlso struct {
							Text     string `xml:",chardata"`
							Resource string `xml:"resource,attr"`
						} `xml:"seeAlso"`
						Identifier struct {
							Text     string `xml:",chardata"`
							Datatype string `xml:"datatype,attr"`
						} `xml:"identifier"`
						CallNumber      string   `xml:"callNumber"`
						LocalCallNumber []string `xml:"localCallNumber"`
						HoldingIssues   string   `xml:"holdingIssues"`
					} `xml:"Item"`
				} `xml:"RDF"`
			} `xml:"metadata"`
		} `xml:"record"`
	} `xml:"ListRecords"`
}

/*
type KENVCONF struct {
	HomeDir   string `json:"homeDir"`
	ExtDir    string `json:"extDir"`
	ExtAppDir string `json:"extAppDir"`
	WebServer struct {
		Listen string `json:"listen"`
	} `json:"web"`
	Solr struct {
		Home      string `json:"home"`
		Serveruri string `json:"serveruri"`
		Corename  string `json:"corename"`
	} `json:"solr"`
	Tika struct {
		Home      string `json:"home"`
		Serveruri string `json:"serveruri"`
	} `json:"tika"`
	Files []FileProcessor `json:"files"`
}
*/

const ContenttypeExcel string = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
const ContenttypePdf string = "application/pdf"
const ContenttypeText string = "text/plain"
const ContenttypeWord string = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"

func ExtnameToMediaType(extname string) string {
	ct := "application/octet-stream"
	switch extname {
	case ".xlsx":
		ct = ContenttypeExcel
	case ".pdf":
		ct = ContenttypePdf
	case ".txt":
		ct = ContenttypeText
	case ".docx":
		ct = ContenttypeWord
	}
	return ct
}

func SolrClearDocument(uriaddress string, corename string) error {

	uri := uriaddress + "/solr"
	si, err := solr.NewSolrInterface(uri, corename)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
		return err
	}

	_, err = si.DeleteAll()
	if err != nil {
		log.Fatal().Err(err)
		return err
	}

	return nil
}

// SolrQuery は、Solrに検索を投げます
// TODO: 引数要改良 、solrに接続する箇所も改良。毎回接続するのは問題。
func SolrQuery(uriaddress string, corename string, qs string) (*solr.SolrResult, error) {
	uri := uriaddress + "/solr"
	si, err := solr.NewSolrInterface(uri, corename)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
		return nil, err
	}

	//opts := &solr.ReadOptions{Rows: 20, Debug: solr.DebugTypeQuery}
	q := solr.NewQuery()

	// cut whitespace and zenkaku space
	qs = strings.TrimRight(qs, " 　")
	if qs == "" {
		q.Q("*:*")
	} else {
		q.Q("contents:" + qs)
	}

	q.Start(0)
	q.Rows(20)

	q.SetParam("hl", "true")
	q.SetParam("hl.fl", "contents")
	q.SetParam("hl.simple.pre", "<em>")
	q.SetParam("hl.simple.post", "</em>")

	s := si.Search(q)
	res, err := s.Result(nil)
	if err != nil {
		log.Err(err).Msgf("fail query: q=%s", q)
		return nil, err
	}

	log.Info().Msgf("NumFound/FetchDocs/Highlighting:%d/%d/%d", res.Results.NumFound, len(res.Results.Docs), len(res.Highlighting))

	return res, nil
}

// GenerateTextIndex は、テキスト形式のファイルの索引を作成します
// 1ファイルで Solr の1ドキュメントとする
func GenerateTextIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {

	var cells []string

	// Find the review items
	doc.Find("p").Each(func(rindex int, pselection *goquery.Selection) {
		// For each item found
		text := strings.ReplaceAll(pselection.Text(), "&#13;", " ")
		text = strings.ReplaceAll(text, " ", "")

		//for debug
		//fmt.Println(text)

		cells = append(cells, text)
	})

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	mid := fmt.Sprintf("%s%d", filename, 0)

	vparams := map[string]string{
		"mediatype":  mediatype,
		"foldername": foldername,
		"filename":   basename,
		"title":      basename,
	}

	err := SolrAddDocument(si, mid, "FILE", cells, vparams)
	if err != nil {
		log.Fatal().Err(err)
	}
	fmt.Print(".")

	//fmt.Println(res.Header)

	return "ok"
}

// GenerateWordxIndex は MS WORD(.docx)形式のファイルの索引を作る
// 1ファイルで Solr の1ドキュメントとする
func GenerateWordxIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {

	//fmt.Println("docx")

	var cells []string

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename

	doc.Find("meta").Each(func(i int, s *goquery.Selection) {
		if name, _ := s.Attr("name"); name == "dc:title" {
			c, _ := s.Attr("content")
			if c != "" {
				title = c
			}
		}
	})

	// Find the review items
	doc.Find("p").Each(func(rindex int, pageselection *goquery.Selection) {
		// For each item found
		text := strings.Replace(pageselection.Text(), "\n", " ", -1)
		text = strings.Replace(text, " ", "", -1)
		//text = rep_space.ReplaceAllString(text, "")

		//for debug
		//fmt.Println(text)

		cells = append(cells, text)
	})

	//fmt.Printf("title:%s\n", title)

	mid := fmt.Sprintf("%s%d", filename, 0)
	//wr := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Title: title, Contents: cells}

	vparams := map[string]string{
		"mediatype":  mediatype,
		"foldername": foldername,
		"filename":   basename,
		"title":      title,
	}

	err := SolrAddDocument(si, mid, "FILE", cells, vparams)
	if err != nil {
		log.Fatal().Err(err)
	}
	fmt.Print(".")

	return "ok"
}

// GeneratePdfIndex は、PDF(.pdf)形式のファイルの索引を作る
// PDFの1ページで Solr の1ドキュメントとする
func GeneratePdfIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename

	// Find the review items
	doc.Find("div.page").Each(func(rindex int, pageselection *goquery.Selection) {
		// For each item found
		text := strings.Replace(pageselection.Text(), "\n", " ", -1)
		text = strings.Replace(text, " ", "", -1)
		//text = rep_space.ReplaceAllString(text, "")

		mid := fmt.Sprintf("%s%d", filename, rindex)
		//doc := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Title: title, Contents: cells}
		vparams := map[string]string{
			"mediatype":  mediatype,
			"foldername": foldername,
			"filename":   basename,
			"title":      title,
		}

		err := SolrAddDocument(si, mid, "FILE", []string{text}, vparams)
		if err != nil {
			log.Fatal().Err(err)
		}
		fmt.Print(".")
	})

	return "ok"
}

// GenerateExcelIndex は Excel(.xlsx)形式のファイルの索引を作る
// Excelの1行で Solr の1ドキュメントとする
func GenerateExcelIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {
	//TODO:対象外とするシート名の受け渡しは要改良
	excludesheetnames := []string{"注意書き"}

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename
	doc.Find("meta").Each(func(i int, s *goquery.Selection) {
		if name, _ := s.Attr("name"); name == "dc:title" {
			c, _ := s.Attr("content")
			if c != "" {
				title = c
			}
		}
	})

	doc.Find("body div").Each(func(rindex int, sheetselection *goquery.Selection) {
		sheetname := sheetselection.Find("h1").Text()

		if !ArrayContains(excludesheetnames, sheetname) {

			// Find the review items
			sheetselection.Find("table tbody tr").Each(func(rindex int, rowselection *goquery.Selection) {
				// For each item found
				var cells []string

				innerselection := rowselection.Find("td")
				innerselection.Each(func(cindex int, cellsel *goquery.Selection) {
					//fmt.Printf("cell %d %d: %s\n", rindex, cindex, cellsel.Text())

					cells = append(cells, cellsel.Text())
				})

				// 情報がある行のみ索引を作成する（空行は索引に含めない）
				if len(cells) > 0 {
					mid := fmt.Sprintf("%s%s%d", filename, sheetname, rindex)

					vparams := map[string]string{
						"mediatype":  mediatype,
						"foldername": foldername,
						"filename":   basename,
						"sheetname":  sheetname,
						"title":      title,
					}

					err := SolrAddDocument(si, mid, "FILE", cells, vparams)
					if err != nil {
						log.Fatal().Err(err)
						//return err
					}
					fmt.Print(".")
				}
			})
		}

	})

	return "ok"
}

func ImportFromFileNCNDLRDF(files []string, solrserveruri string, solrcorename string) error {
	uri := solrserveruri + "/solr"
	si, err := solr.NewSolrInterface(uri, solrcorename)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}
	status, qtime, err := si.Ping()
	if err != nil {
		fmt.Printf("Solr core ping:ng (%s %s)\n", solrserveruri, solrcorename)
		return err
	}
	log.Debug().Msgf("Solr Ping status:%s qtime:%d\n", status, qtime)

	successCount := 0
	for _, filename := range files {
		fmt.Printf("%d/%d filename:%s\n", successCount+1, len(files), filename)

		fi, err := os.Open(filename)
		if err != nil {
			return errors.New(fmt.Sprintf("os: Unable to open file [%s]", filename))
		}

		data, err := ioutil.ReadAll(fi)
		if err != nil {
			return err
		}
		dcndloaipmh := DCNDLOAIPMH{}
		err = xml.Unmarshal(data, &dcndloaipmh)
		if err != nil {
			fmt.Printf("error: %v", err)
			fi.Close()
			return err
		}

		for _, r := range dcndloaipmh.ListRecords.Record {
			materialid := r.Header.Identifier
			bibresource := r.Metadata.RDF.BibResource[0]
			title := bibresource.Title.Description.Value
			title_transcription := bibresource.Title.Description.Transcription
			uniform_title := bibresource.UniformTitle.Description.Value
			uniform_title_transcription := bibresource.UniformTitle.Description.Transcription
			volume := bibresource.Volume.Description.Value
			volume_transcription := bibresource.Volume.Description.Transcription
			volume_title := bibresource.VolumeTitle.Description.Value
			volume_title_transcription := bibresource.VolumeTitle.Description.Transcription
			alternative := bibresource.Alternative.Description.Value
			alternative_transcription := bibresource.Alternative.Description.Transcription
			//TODO: データ確認
			//alternative_volume
			//alternative_volume_title
			series_title := ""
			series_title_transcription := ""
			if len(bibresource.SeriesTitle) > 0 {
				//TODO: 配列対応
				series_title = bibresource.SeriesTitle[0].Description.Value
				series_title_transcription = bibresource.SeriesTitle[0].Description.Transcription
			}
			edition := bibresource.Edition

			creator := ""
			creator_transcription := ""
			creator_identifier := ""
			creator_literal := ""

			//TODO: 配列対応
			if len(bibresource.Creator) > 0 {
				creator = bibresource.Creator[0].Agent.Name
				creator_transcription = bibresource.Creator[0].Agent.Transcription
				//TODO: creatorにすべきかagentに収めるか
				creator_identifier = bibresource.Creator[0].Agent.About
				creator_literal = bibresource.Creator[0].Text
			}
			//TODO: データ確認
			//creator_alternative_literal :=

			fmt.Println(materialid, title, title_transcription, uniform_title, uniform_title_transcription)
			fmt.Println(volume, volume_transcription, volume_title, volume_title_transcription)
			fmt.Println(alternative, alternative_transcription)
			fmt.Println(series_title, series_title_transcription, edition)
			fmt.Println(creator, creator_transcription, creator_identifier, creator_literal)
		}
		fi.Close()
	}

	return nil
}

func ImportFromISBNFile(files []string, solrserveruri string, solrcorename string) error {
	uri := solrserveruri + "/solr"
	si, err := solr.NewSolrInterface(uri, solrcorename)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}

	status, qtime, err := si.Ping()
	if err != nil {
		fmt.Printf("Solr core ping:"+NGLBL+" (%s %s)\n", solrserveruri, solrcorename)
		return err
	}
	log.Debug().Msgf("Solr Ping status:%s qtime:%d\n", status, qtime)

	successCount := 0
	for _, filename := range files {
		fmt.Printf("%d/%d filename:%s\n", successCount+1, len(files), filename)

		fi, err := os.Open(filename)
		if err != nil {
			return errors.New(fmt.Sprintf("os: Unable to open file [%s]", filename))
		}

		reader := bufio.NewReaderSize(fi, 4096)
		for {
			line, _, err := reader.ReadLine()
			if err != nil {
				if err == io.EOF {
					break
				}
				fmt.Println(err)
			}

			isbn := string(line)
			fmt.Println(isbn)

			isbn = strings.TrimSpace(isbn)
			if isbn == "" {
				continue
			}
			data, err := FetchMaterialFromNDLByISBN(isbn)
			if err != nil {
				fmt.Println(err)
			}

			//fmt.Printf("%+v", data)
			//fmt.Println(data.NumberOfRecords)
			fmt.Printf("isbn:%s r:%s\n", isbn, data.NumberOfRecords)
			numOfRecords, _ := strconv.Atoi(data.NumberOfRecords)
			if numOfRecords > 0 {
				fmt.Println(data.Records.Record[0].RecordData.Dc.Title)
			}
		}
		fi.Close()
	}
	return nil
}

func FetchMaterialFromNDLByISBN(isbn string) (*SearchRetrieveResponse, error) {
	client := &http.Client{
		Transport: &http.Transport{
			Proxy: http.ProxyFromEnvironment,
			// DisableKeepAlives: true,
			MaxIdleConns:    10,
			IdleConnTimeout: 30 * time.Second,
			//DisableCompression: true,
		},
	}
	endpoint := "https://iss.ndl.go.jp/api/sru"

	data := SearchRetrieveResponse{}

	u, err := url.Parse(endpoint)
	if err != nil {
		return &data, err
	}
	q := u.Query()
	q.Add("operation", "searchRetrieve")
	//q.Add("version", "1.2")
	q.Add("recordSchema", "dcndl")
	q.Add("onlyBib", "true")
	q.Add("maximumRecords", "1")
	q.Add("query", fmt.Sprintf("isbn=%s", isbn))
	u.RawQuery = q.Encode()

	req, err := http.NewRequest(http.MethodGet, u.String(), nil)
	if err != nil {
		panic(err)
	}

	req.Header.Set("User-Agent", fmt.Sprintf("kassis/%s", VERSION))

	result := new(httpstat.Result)
	ctx := httpstat.WithHTTPStat(req.Context(), result)
	req = req.WithContext(ctx)

	resp, err := client.Do(req)
	if err != nil {
		panic(err)
	}

	//https://iss.ndl.go.jp/api/sru?operation=searchRetrieve&query=isbn%3D9784480689108&recordSchema=dcndl&onlyBib=true
	//fmt.Println(string(body))
	body, err := ioutil.ReadAll(resp.Body)
	defer resp.Body.Close()
	if err != nil {
		panic(err)
	}
	if resp.StatusCode != 200 {
		fmt.Println("Error Response:", resp.Status)
		resp.Body.Close()
		return &data, err
	}

	fmt.Println(len(string(body)))

	result.End(time.Now())

	if err := xml.Unmarshal(body, &data); err != nil {
		fmt.Println(err)
		return &data, err
	}

	//fmt.Println(data)

	return &data, nil
}

func ImportFromFile(files []string, tikaserveruri string, solrserveruri string, solrcorename string) error {

	uri := solrserveruri + "/solr"
	si, err := solr.NewSolrInterface(uri, solrcorename)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}
	status, qtime, err := si.Ping()
	if err != nil {
		fmt.Printf("Solr core ping:ng (%s %s)\n", solrserveruri, solrcorename)
		return err
	}
	log.Debug().Msgf("Solr Ping status:%s qtime:%d\n", status, qtime)

	//Create connection with tika server
	tikaclient := tika.NewClient(nil, tikaserveruri)

	successCount := 0
	for _, filename := range files {
		fmt.Printf("%d/%d filename:%s\n", successCount+1, len(files), filename)
		//Get the file and open it
		file, err := os.Open(filename)
		if err != nil {
			return errors.New(fmt.Sprintf("os: Unable to open file [%s]", filename))
		}

		//TODO: close file
		//defer file.Close()

		extname := filepath.Ext(filename)
		contentType := ExtnameToMediaType(extname)
		header := http.Header{}
		header.Add("Content-Type", contentType)
		//Read the content from file
		body, err := tikaclient.ParseWithHeader(context.Background(), file, header)
		if err != nil {
			//log.Fatal().Err(err)
			if err.Error() == "response code 422" {
				// for empty file (0byte stream)
				continue
			}
			if strings.Contains(err.Error(), "connectex: No connection could be made because the target machine actively refused it.") {
				// cannot connect to tike server
				return err
			}
			fmt.Println("err?:")
			fmt.Println(err.Error())
		}

		// Load the HTML document
		doc, err := goquery.NewDocumentFromReader(strings.NewReader(body))
		if err != nil {
			log.Fatal().Err(err)
		}

		//for debug
		//fmt.Print("@1")
		//fmt.Print(body)

		//TODO: 拡張子とフォーマットのMAPから選択したい
		switch extname {
		case ".xlsx":
			GenerateExcelIndex(si, filename, contentType, doc)
		case ".pdf":
			GeneratePdfIndex(si, filename, contentType, doc)
		case ".docx":
			GenerateWordxIndex(si, filename, contentType, doc)
		case ".txt":
			GenerateTextIndex(si, filename, contentType, doc)
		default:
			fmt.Printf("\nunknown format: skip [%s]\n", filename)
		}
		fmt.Printf("\n")
		successCount++
	}

	if si != nil {
		_, err := si.Commit()
		if err != nil {
			fmt.Println(err)
		}
	}

	fmt.Printf("success_count=%d\n", successCount)

	return nil
}

// 内部関数
func netFetch(uri string, filename string, cfg *KENVCONF) (*bytes.Buffer, error) {
	bytebuf := new(bytes.Buffer)

	// Getリクエスト
	res, err := http.Get(uri)
	if err != nil {
		log.Err(err).Msg("error occurred")
		return bytebuf, err
	}

	defer func(Body io.ReadCloser) {
		err := Body.Close()
		if err != nil {

		}
	}(res.Body)

	log.Debug().Msgf("%s", res.Header)

	// 読み取り
	buf, _ := ioutil.ReadAll(res.Body)

	bReader := bytes.NewReader(buf)

	_, err = io.Copy(bytebuf, bReader)
	if err != nil {
		return nil, err
	}
	err = ioutil.WriteFile(filename, bytebuf.Bytes(), os.ModePerm)
	if err != nil {
		return nil, err
	}

	return bytebuf, nil
}

func WebCrawler(uri string, cfg *KENVCONF) (string, error) {

	foldername := "foo"
	str1 := url.PathEscape(uri)
	filename := filepath.Join(cfg.ExtDir, foldername, str1)

	log.Debug().Str("filename", filename)

	byteBuf, err := netFetch(uri, filename, cfg)
	if err != nil {
		return "ng", err
	}

	// HTMLパース
	//fmt.Println(bytebuf)
	doc, _ := goquery.NewDocumentFromReader(byteBuf)

	doc.Find("a").Each(func(_ int, s *goquery.Selection) {
		uri, _ := s.Attr("href")
		fmt.Println(uri)
	})

	return "ok", nil
}
