import requests
import flask
import flask_cors
from flask import render_template, jsonify

base_url = "https://data.etabus.gov.hk/"
app = flask.Flask(__name__)
flask_cors.CORS(app)

@app.route('/', methods=['GET'])
def home():
    return render_template("index.html")

@app.route('/bus/routes', methods=['GET'])
def get_bus_routes():
    endpoint = "v1/transport/kmb/route"
    try:
        response = requests.get(base_url + endpoint, timeout=10)
        data = response.json()
        outbound_routes = [
            route for route in data.get("data", [])
            if route.get("bound") == "O"
        ]
        return jsonify({"data": outbound_routes})
    except requests.RequestException as e:
        return jsonify({"error": str(e)}), 500

@app.route('/bus/stop/<stop_id>', methods=['GET'])
def get_stop(stop_id):
    endpoint = f"v1/transport/kmb/stop/{stop_id}"
    try:
        response = requests.get(base_url + endpoint, timeout=10)
        if response.status_code == 200:
            return jsonify(response.json())
        else:
            return jsonify({"error": f"Received status code {response.status_code}"}), response.status_code
    except requests.RequestException as e:
        return jsonify({"error": str(e)}), 500

@app.route('/bus/route/<route_id>/<bound>/<service_type>/stops', methods=['GET'])
def get_route_stops(route_id, bound, service_type):
    endpoint = f"v1/transport/kmb/route-stop/{route_id}/{bound}/{service_type}"
    try:
        response = requests.get(base_url + endpoint, timeout=10)
        if response.status_code == 200:
            return jsonify(response.json())
        else:
            return jsonify({"error": f"Received status code {response.status_code}"}), response.status_code
    except requests.RequestException as e:
        return jsonify({"error": str(e)}), 500

@app.route('/bus/stop/<stop_id>/arrivals', methods=['GET'])
def get_stop_arrivals(stop_id):
    endpoint = f"v1/transport/kmb/stop-eta/{stop_id}"
    try:
        response = requests.get(base_url + endpoint, timeout=10)
        if response.status_code == 200:
            return jsonify(response.json())
        else:
            return jsonify({"error": f"Received status code {response.status_code}"}), response.status_code
    except requests.RequestException as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)